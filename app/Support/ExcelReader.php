<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelReader
{
    // 0-indexed, string cells
    public static function toArray(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($sheet->toArray(null, true, true, false) as $row) {
            $rows[] = array_map(
                fn ($value) => $value === null ? '' : (string) $value,
                $row,
            );
        }

        return $rows;
    }
}
