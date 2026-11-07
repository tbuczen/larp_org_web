<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Reads a character CSV as exported by Excel, LibreOffice or Google Sheets:
 * - delimiter ";" "," or tab (detected from the header line)
 * - UTF-8 (with or without BOM) or Windows-1250 (Polish Excel)
 * - headers in Polish or English, any case, with or without diacritics
 */
final class CsvReader
{
    public const MAX_BYTES = 1_000_000;
    public const MAX_ROWS = 1000;

    /** Internal column => accepted headers (compared as lowercase ASCII slugs). */
    public const COLUMNS = [
        'name' => ['nazwa', 'imie', 'imie-nazwa', 'postac', 'name', 'character'],
        'slug' => ['slug', 'adres'],
        'teaser' => ['zajawka', 'teaser', 'krotki-opis', 'short-description'],
        'description' => ['opis', 'pelny-opis', 'description'],
        'casting' => ['obsada', 'plec', 'casting', 'gender'],
        'availability' => ['dostepnosc', 'status', 'availability'],
        'position' => ['kolejnosc', 'pozycja', 'position', 'order'],
        'gameplay' => ['rozgrywka', 'gameplay', 'tagi-rozgrywki', 'gameplay-tags'],
        'triggers' => ['triggery', 'trigger', 'triggers', 'trigger-tags'],
    ];

    public function read(string $content): CsvTable
    {
        if ('' === trim($content)) {
            throw new CsvImportException('import.error.empty');
        }
        if (\strlen($content) > self::MAX_BYTES) {
            throw new CsvImportException('import.error.too_large', ['%max%' => self::MAX_BYTES / 1000 .' kB']);
        }

        [$content, $encoding] = $this->toUtf8($content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $delimiter = $this->detectDelimiter(strtok($content, "\n") ?: '');

        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw new \RuntimeException('Cannot open a temporary stream.');
        }
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, null, $delimiter, '"', '');
        if (false === $header) {
            throw new CsvImportException('import.error.empty');
        }

        $slugger = new AsciiSlugger();
        $columns = [];
        $ignored = [];
        foreach ($header as $index => $title) {
            $key = $slugger->slug(trim((string) $title))->lower()->toString();
            $column = null;
            foreach (self::COLUMNS as $name => $aliases) {
                if (\in_array($key, $aliases, true)) {
                    $column = $name;
                    break;
                }
            }
            if (null !== $column && !isset($columns[$column])) {
                $columns[$column] = $index;
            } elseif ('' !== trim((string) $title)) {
                $ignored[] = trim((string) $title);
            }
        }
        if (!isset($columns['name'])) {
            throw new CsvImportException('import.error.no_name_column', ['%headers%' => implode(', ', array_map('strval', $header))]);
        }

        $rows = [];
        $line = 1;
        while (false !== ($cells = fgetcsv($stream, null, $delimiter, '"', ''))) {
            ++$line;
            $cells = array_map(static fn ($c): string => (string) $c, $cells);
            if ([] === array_filter($cells, static fn (string $c): bool => '' !== trim($c))) {
                continue; // empty line
            }
            if (\count($rows) >= self::MAX_ROWS) {
                throw new CsvImportException('import.error.too_many_rows', ['%max%' => self::MAX_ROWS]);
            }
            $rows[] = ['line' => $line, 'cells' => $cells];
        }
        fclose($stream);

        if ([] === $rows) {
            throw new CsvImportException('import.error.no_rows');
        }

        return new CsvTable($columns, $rows, $delimiter, $encoding, $ignored, \count($header));
    }

    /** @return array{0: string, 1: string} content in UTF-8 and the detected source encoding */
    private function toUtf8(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return [substr($content, 3), 'UTF-8'];
        }
        if (mb_check_encoding($content, 'UTF-8')) {
            return [$content, 'UTF-8'];
        }
        $converted = iconv('WINDOWS-1250', 'UTF-8//IGNORE', $content);

        return [false === $converted ? $content : $converted, 'Windows-1250'];
    }

    private function detectDelimiter(string $headerLine): string
    {
        $counts = [';' => substr_count($headerLine, ';'), ',' => substr_count($headerLine, ','), "\t" => substr_count($headerLine, "\t")];
        arsort($counts);

        return 0 === reset($counts) ? ';' : (string) array_key_first($counts);
    }
}
