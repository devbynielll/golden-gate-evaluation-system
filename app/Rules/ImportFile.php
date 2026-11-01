<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class ImportFile implements ValidationRule
{
    public function __construct(private array $extensions) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Use a valid import file.');
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $nativeMime = Validator::make(['file' => $value], ['file' => 'mimes:'.$extension])->passes();
        $plainCsv = $extension === 'csv' && $value->getMimeType() === 'text/plain';
        if (! in_array($extension, $this->extensions, true) || (! $nativeMime && ! $plainCsv)) {
            $fail('Use a supported import file matching its extension.');
            return;
        }

        // Preserve existing Word support; spreadsheet content must match its declared format.
        if (in_array($extension, ['csv', 'xls', 'xlsx'], true)) {
            try {
                $path = $value->getRealPath();
                $format = strtolower(IOFactory::identify($path, ['Xls', 'Xlsx', 'Csv']));
                if ($format !== $extension) {
                    throw new \RuntimeException('Mismatched file contents');
                }
                if ($plainCsv) {
                    $text = mb_convert_encoding(file_get_contents($path), 'UTF-8', Csv::guessEncoding($path));
                    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
                    // The CSV reader also accepts HTML/plain prose. Do not admit disguised
                    // markup/JSON documents or binary controls through the text/plain exception.
                    if (trim($text) === '' || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)
                        || preg_match('/^\s*[<{\[]/', $text)) {
                        throw new \RuntimeException('Not CSV content');
                    }
                }
            } catch (\Throwable $e) {
                $fail('Use a readable import file matching its extension.');
            }
        }
    }
}
