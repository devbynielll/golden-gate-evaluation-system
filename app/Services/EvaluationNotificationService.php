<?php

namespace App\Services;

use App\Models\{Evaluation, Office, ReviewPeriod, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class EvaluationNotificationService
{
    // Notification disclosure only: legacy report calculations and access remain unchanged.
    public const MINIMUM_STUDENTS = 5;
    public const TYPE = 'evaluation-period-aggregate';

    private function scopes(int $periodId): array
    {
        $base = Evaluation::where('review_period_id', $periodId)->whereNotNull('submitted_at')
            ->whereHas('answers');
        $scopes = [];
        if ((clone $base)->where('evaluatee_type', 'faculty')->distinct()->count('student_id') >= self::MINIMUM_STUDENTS) {
            $scopes[] = ['kind' => 'faculty', 'office_id' => null];
        }
        foreach ((clone $base)->where('evaluatee_type', 'office')->whereNotNull('office_id')
            ->select('office_id')->groupBy('office_id')->havingRaw('COUNT(DISTINCT student_id) >= ?', [self::MINIMUM_STUDENTS])->pluck('office_id') as $officeId) {
            $scopes[] = ['kind' => 'office', 'office_id' => (int) $officeId];
        }
        return $scopes;
    }

    public function generate(int $periodId): void
    {
        DB::transaction(function () use ($periodId) {
            $period = ReviewPeriod::whereKey($periodId)->sharedLock()->first();
            if (! $period || $period->is_submission_open) return;
            $scopes = $this->scopes($periodId);
            if (! $scopes) return;
            User::whereIn('role', User::ADMIN_ROLES)->whereNotNull('email_verified_at')->orderBy('id')
                ->chunkById(100, function ($users) use ($period, $scopes) {
                    $rows = [];
                    foreach ($users as $user) foreach ($scopes as $scope) {
                        if (! $this->canAccess($user, $scope['kind'], $scope['office_id'])) continue;
                        // Stable UUID per recipient + period + scope. Retries/reclosures never reset read state.
                        $hash = hash('sha256', $user->getMorphClass().':'.$user->id.':'.$period->id.':'.$scope['kind'].':'.($scope['office_id'] ?? 'all'));
                        $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);
                        $rows[] = ['id' => $id, 'type' => self::TYPE, 'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id,
                            'review_period_id' => $period->id, 'evaluation_kind' => $scope['kind'], 'office_id' => $scope['office_id'],
                            'data' => '{}', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()];
                    }
                    if ($rows) DB::table('notifications')->insertOrIgnore($rows);
                });
        }, 3);
    }

    private function canAccess(User $user, string $kind, ?int $officeId): bool
    {
        return $kind === 'faculty'
            ? in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_DEPARTMENT_ADMIN], true)
            : $kind === 'office' && ($user->isSuperAdmin() || ($user->isOfficeAdmin() && $officeId !== null && (int) $user->office_id === $officeId));
    }

    private function visible(User $user): Builder
    {
        // Re-read role and ownership, including when a session holds a stale model.
        $user = User::whereKey($user->id)->sharedLock()->firstOrFail();
        $query = DatabaseNotification::where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->where('type', self::TYPE);
        if (! $user->hasVerifiedEmail() || ! $user->isAdmin()) return $query->whereRaw('1 = 0');
        if ($user->isDepartmentAdmin()) $query->where('evaluation_kind', 'faculty')->whereNull('office_id');
        elseif ($user->isOfficeAdmin()) $query->where('evaluation_kind', 'office')->where('office_id', $user->office_id ?? 0);
        // Recheck both release state and threshold. Neither unread counts nor read writes bypass this gate.
        return $query->whereIn('review_period_id', ReviewPeriod::where('is_submission_open', false)->select('id'))
            ->where(function ($q) {
                $q->where(function ($q) { $q->where('evaluation_kind', 'faculty')->whereNull('office_id')
                    ->whereRaw('(SELECT COUNT(DISTINCT e.student_id) FROM evaluations e WHERE e.review_period_id = notifications.review_period_id AND e.evaluatee_type = ? AND e.submitted_at IS NOT NULL AND EXISTS (SELECT 1 FROM evaluation_answers a WHERE a.evaluation_id = e.id)) >= ?', ['faculty', self::MINIMUM_STUDENTS]); });
                $q->orWhere(function ($q) { $q->where('evaluation_kind', 'office')->whereNotNull('office_id')
                    ->whereRaw('(SELECT COUNT(DISTINCT e.student_id) FROM evaluations e WHERE e.review_period_id = notifications.review_period_id AND e.office_id = notifications.office_id AND e.evaluatee_type = ? AND e.submitted_at IS NOT NULL AND EXISTS (SELECT 1 FROM evaluation_answers a WHERE a.evaluation_id = e.id)) >= ?', ['office', self::MINIMUM_STUDENTS]); });
            });
    }

    public function inbox(User $user, int $page): array
    {
        return DB::transaction(function () use ($user, $page) {
            ReviewPeriod::orderBy('id')->sharedLock()->get(['id']);
            $query = $this->visible($user);
            $unread = (clone $query)->whereNull('read_at')->count();
            // Order by period and scope, never submission or notification timestamps.
            $items = $query->orderByDesc('review_period_id')->orderBy('evaluation_kind')->orderBy('office_id')->orderBy('id')->paginate(10, page: $page);
            $periods = ReviewPeriod::whereIn('id', $items->pluck('review_period_id'))->get()->keyBy('id');
            $offices = Office::whereIn('id', $items->pluck('office_id')->filter())->get()->keyBy('id');
            return ['unread' => $unread, 'items' => $items->map(function ($item) use ($periods, $offices) {
                $period = $periods[$item->review_period_id];
                $faculty = $item->evaluation_kind === 'faculty';
                $office = $faculty ? null : $offices->get($item->office_id);
                return ['id' => $item->id, 'kind' => $item->evaluation_kind, 'read' => $item->read_at !== null,
                    'title' => $faculty ? 'Faculty evaluation activity' : 'Office evaluation activity',
                    'subject' => $faculty ? 'Faculty — all authorized academic departments' : ($office ? $office->code.' — '.$office->name : 'Office'),
                    'period' => $period->semester.' | '.$period->academic_year,
                    'message' => 'Aggregated evaluation activity is available for this officially closed review period.',
                    'url' => route($faculty ? 'reports.faculty' : 'reports.office', ['period_id' => $period->id, ...($faculty ? [] : ['search' => $office?->name])])];
            })->values()->all(), 'page' => $items->currentPage(), 'lastPage' => $items->lastPage()];
        }, 3);
    }

    public function setRead(User $user, string $id, bool $read): void
    {
        DB::transaction(function () use ($user, $id, $read) {
            ReviewPeriod::orderBy('id')->sharedLock()->get(['id']);
            $notification = $this->visible($user)->whereKey($id)->lockForUpdate()->firstOrFail();
            $notification->forceFill(['read_at' => $read ? now() : null])->save();
        }, 3);
    }
}
