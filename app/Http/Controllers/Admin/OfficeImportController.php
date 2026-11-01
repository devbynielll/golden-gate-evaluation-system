<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesExcelImports;
use App\Http\Controllers\Controller;
use App\Jobs\ImportOfficeRowsJob;
use App\Models\ImportBatch;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OfficeImportController extends Controller
{
    use HandlesExcelImports;

    public function preview(Request $request): JsonResponse
    {
        return response()->json($this->storeUpload($request));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'startRow' => ['required', 'integer', 'min:1'],
        ]);

        $rows = $this->resolveUploadedRows($data['token']);
        $dataRows = array_slice($rows, $data['startRow'] - 1, null, true);

        $batch = ImportBatch::create([
            'type' => ImportBatch::TYPE_OFFICE,
            'total_rows' => count($dataRows),
            'created_by' => $request->user()->id,
        ]);

        $jobs = array_map(
            fn (array $chunk) => new ImportOfficeRowsJob(
                $chunk, $data['mapping'], array_key_first($chunk) + 1, $batch->id,
            ),
            $this->chunkRows($dataRows),
        );

        $path = Cache::pull("import-upload:{$data['token']}")['path'] ?? null;
        Bus::batch($jobs)
            ->onQueue('imports')
            ->then(function (Batch $busBatch) use ($batch, $path) {
                $batch->markCompleted();
                if ($path) {
                    Storage::delete($path);
                }
            })
            ->dispatch();

        return response()->json(['batchId' => $batch->id]);
    }
}
