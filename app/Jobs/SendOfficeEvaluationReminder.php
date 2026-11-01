<?php
namespace App\Jobs;

use App\Mail\EvaluationReminder;
use App\Models\{Evaluation, Office, ReviewPeriod, Student};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\{Cache, Mail};

class SendOfficeEvaluationReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    public function __construct(public int $studentId, public int $periodId, public ?int $officeId = null, public ?int $actorId = null) {}
    public static function pending(Student $student, ReviewPeriod $period, ?int $officeId = null): array
    {
        if (!$period->is_active || !$period->is_submission_open) return [];
        return Office::where(fn($q)=>$q->whereNull('department_id')->orWhere('department_id',$student->department()?->id))
            ->when($officeId,fn($q)=>$q->whereKey($officeId))->whereNotIn('id',Evaluation::where('student_id',$student->id)->where('review_period_id',$period->id)
                ->where('evaluatee_type',Evaluation::TYPE_OFFICE)->whereNotNull('submitted_at')->pluck('office_id')->filter())
            ->orderBy('name')->pluck('name')->all();
    }
    public function handle(): void
    {
        $student=Student::find($this->studentId);$period=ReviewPeriod::find($this->periodId);
        if (!$student || !$period) return;
        if ($this->actorId) {
            $actor=\App\Models\User::find($this->actorId);
            if (!$actor || (!$actor->isSuperAdmin() && (!$actor->isOfficeAdmin() || !$this->officeId || (int)$actor->office_id !== $this->officeId))) return;
        }
        $key="office-reminder:{$this->periodId}:{$this->studentId}";
        $lock=Cache::lock($key.':lock',60);
        if (!$lock->get()) { $this->release(15);return; }
        try {
            if (Cache::has($key.':sent')) return;
            $pending=self::pending($student,$period,$this->officeId);
            if (!$pending) return;
            Mail::to($student->email)->send(new EvaluationReminder($student,'their pending campus offices',$pending));
            Cache::put($key.':sent',true,now()->addMinutes(10));
        } finally { $lock->release(); }
    }
}
