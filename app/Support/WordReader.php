<?php

namespace App\Support;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text as TextElement;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class WordReader
{
    // table rows if any exist, else columns inferred from plain text lines
    public static function toArray(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $readerName = $extension === 'doc' ? 'MsDoc' : 'Word2007';

        $phpWord = IOFactory::load($path, $readerName);

        $tableRows = self::extractTableRows($phpWord);
        if (! empty($tableRows)) {
            return $tableRows;
        }

        return self::linesToRows(self::extractLines($phpWord));
    }

    private static function extractTableRows(PhpWord $phpWord): array
    {
        $rows = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Table) {
                    foreach ($element->getRows() as $row) {
                        $cells = [];
                        foreach ($row->getCells() as $cell) {
                            $cells[] = trim(self::containerText($cell));
                        }
                        $rows[] = $cells;
                    }
                }
            }
        }

        return $rows;
    }

    private static function extractLines(PhpWord $phpWord): array
    {
        $lines = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text = trim(self::elementText($element));
                if ($text !== '') {
                    $lines[] = $text;
                }
            }
        }

        return $lines;
    }

    private static function containerText(AbstractContainer $container): string
    {
        $text = '';
        foreach ($container->getElements() as $element) {
            $text .= self::elementText($element).' ';
        }

        return trim($text);
    }

    private static function elementText($element): string
    {
        if ($element instanceof TextElement) {
            return html_entity_decode((string) $element->getText(), ENT_QUOTES);
        }

        if ($element instanceof AbstractContainer) {
            return self::containerText($element);
        }

        if (method_exists($element, 'getText')) {
            return (string) $element->getText();
        }

        return '';
    }

    // picks whichever delimiter splits the most lines into more than one column
    private static function linesToRows(array $lines): array
    {
        if (empty($lines)) {
            return [];
        }

        $delimiters = ["\t", '/\s{2,}/', ',', '|'];
        $best = null;
        $bestHits = 0;

        foreach ($delimiters as $delimiter) {
            $hits = 0;
            foreach ($lines as $line) {
                if (count(self::split($delimiter, $line)) > 1) {
                    $hits++;
                }
            }
            if ($hits > $bestHits) {
                $bestHits = $hits;
                $best = $delimiter;
            }
        }

        if ($best === null || $bestHits < count($lines) * 0.5) {
            return array_map(fn ($line) => [$line], $lines);
        }

        return array_map(fn ($line) => array_map('trim', self::split($best, $line)), $lines);
    }

    private static function split(string $delimiter, string $line): array
    {
        return str_starts_with($delimiter, '/')
            ? preg_split($delimiter, $line)
            : explode($delimiter, $line);
    }
}
