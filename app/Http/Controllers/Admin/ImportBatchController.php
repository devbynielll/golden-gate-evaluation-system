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

        return response()->json([
            'status' => $importBatch->status,
            'totalRows' => $importBatch->total_rows,
            'processedRows' => $importBatch->processed_rows,
            'successCount' => $importBatch->success_count,
            'failureCount' => $importBatch->failure_count,
            'skippedCount' => $importBatch->skipped_count,
            'enrollmentsCount' => $importBatch->enrollments_count,
            'skipped' => $importBatch->skipped ?? [],
            'errors' => $importBatch->errors ?? [],
        ]);
    }
}
