<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\ExcelReader;
use App\Rules\ImportFile;
use App\Support\WordReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesExcelImports
{
    private const MAX_ROWS = 20000;

    private const WORD_EXTENSIONS = ['doc', 'docx'];

    protected function storeUpload(Request $request): array
    {
        $file = $request->validate([
            'file' => ['bail', 'required', 'file', 'max:5120', new ImportFile(['xlsx', 'xls', 'csv', 'doc', 'docx'])],
        ])['file'];

        $path = $file->store('imports');
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = $this->readRows(Storage::path($path), $extension);
        abort_if(count($rows) > self::MAX_ROWS, 422, 'File has too many rows.');

        $token = (string) Str::uuid();
        Cache::put("import-upload:{$token}", ['path' => $path, 'extension' => $extension], now()->addMinutes(30));

        return [
            'token' => $token,
            'totalRows' => count($rows),
            'previewRows' => array_slice($rows, 0, 15),
        ];
    }

    protected function resolveUploadedRows(string $token): array
    {
        $upload = Cache::get("import-upload:{$token}");
        abort_if(! $upload, 422, 'Upload expired, please re-upload the file.');

        return $this->readRows(Storage::path($upload['path']), $upload['extension']);
    }

    protected function forgetUpload(string $token): void
    {
        $upload = Cache::pull("import-upload:{$token}");
        if ($upload) {
            Storage::delete($upload['path']);
        }
    }

    private function readRows(string $fullPath, string $extension): array
    {
        return in_array($extension, self::WORD_EXTENSIONS, true)
            ? WordReader::toArray($fullPath)
            : ExcelReader::toArray($fullPath);
    }

    protected function chunkRows(array $rows, int $size = 150): array
    {
        return array_chunk($rows, $size, true);
    }
}
