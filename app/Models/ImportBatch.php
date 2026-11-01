<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ImportBatch extends Model
{
    public const TYPE_STUDENT = 'student';

    public const TYPE_FACULTY = 'faculty';

    public const TYPE_OFFICE = 'office';

    public const TYPE_REMINDER = 'reminder';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'type', 'department_id', 'program_id', 'status',
        'total_rows', 'processed_rows', 'success_count', 'failure_count',
        'skipped_count', 'enrollments_count', 'skipped', 'errors', 'bus_batch_id', 'created_by',
    ];

    protected $casts = [
        'errors' => 'array',
        'skipped' => 'array',
    ];

    public function recordSuccess(int $enrollments = 0): void
    {
        DB::table('import_batches')->where('id', $this->id)->update([
            'processed_rows' => DB::raw('processed_rows + 1'),
            'success_count' => DB::raw('success_count + 1'),
            'enrollments_count' => DB::raw('enrollments_count + '.$enrollments),
            'status' => self::STATUS_PROCESSING,
            'updated_at' => now(),
        ]);
    }

    public function recordFailure(int|string $row, string $message): void
    {
        $fresh = static::query()->whereKey($this->id)->first();
        $errors = $fresh?->errors ?? [];

        if (count($errors) < 200) {
            $errors[] = ['row' => $row, 'message' => $message];
        }

        DB::table('import_batches')->where('id', $this->id)->update([
            'processed_rows' => DB::raw('processed_rows + 1'),
            'failure_count' => DB::raw('failure_count + 1'),
            'status' => self::STATUS_PROCESSING,
            'errors' => json_encode($errors),
            'updated_at' => now(),
        ]);
    }

    // not a failure, e.g. student already exists
    public function recordSkip(int|string $row, string $message): void
    {
        $skipped = static::query()->whereKey($this->id)->first()?->skipped ?? [];

        if (count($skipped) < 200) {
            $skipped[] = ['row' => $row, 'message' => $message];
        }

        DB::table('import_batches')->where('id', $this->id)->update([
            'processed_rows' => DB::raw('processed_rows + 1'),
            'skipped_count' => DB::raw('skipped_count + 1'),
            'status' => self::STATUS_PROCESSING,
            'skipped' => json_encode($skipped),
            'updated_at' => now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }
}
