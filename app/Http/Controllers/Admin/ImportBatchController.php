<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportBatchController extends Controller
{
    // only your own batches
    public function show(Request $request, ImportBatch $importBatch): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || (int) $importBatch->created_by === $user->id, 403, 'That import belongs to another admin.');

        // Row counters are committed with student creation. Completion must not depend
        // on the final queue callback surviving a worker restart or a network retry.
        if ($importBatch->status !== ImportBatch::STATUS_COMPLETED) {
            if ($importBatch->processed_rows >= $importBatch->total_rows) {
                $importBatch->markCompleted();
            } elseif ($importBatch->bus_batch_id) {
                $busBatch = \Illuminate\Support\Facades\Bus::findBatch($importBatch->bus_batch_id);
                if ($busBatch && ($busBatch->cancelled() || $busBatch->finished())) {
                    $importBatch->markFailed();
                }
            }
        }

        return response()->json([
            'status' => $importBatch->status,
            'totalRows' => $importBatch->total_rows,
            'processedRows' => $importBatch->processed_rows,
            'successCount' => $importBatch->success_count,
            'failureCount' => $importBatch->failure_count,
            'skippedCount' => $importBatch->skipped_count,
            'regularCount' => $importBatch->regular_count,
            'irregularCount' => $importBatch->irregular_count,
            'message' => $importBatch->status === ImportBatch::STATUS_FAILED ? 'Import interrupted. Some records may already be saved. Review the counts before importing again; contact the administrator to inspect the failed queue job.' : null,
            'enrollmentsCount' => $importBatch->enrollments_count,
            'skipped' => $importBatch->skipped ?? [],
            'errors' => $importBatch->errors ?? [],
        ]);
    }
}
