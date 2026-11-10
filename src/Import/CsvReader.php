<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Reads a character CSV as exported by Excel, LibreOffice or Google Sheets:
 * - delimiter ";" "," or tab (detected from the header line)
 * - UTF-8 (with or without BOM) or Windows-1250 (Polish Excel)
 * - headers in Polish or English, any case, with or without diacritics, or any
 *   names at all with an explicit column mapping; also files without a header row
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

    /**
     * Reads the file. Columns come from $mapping (column index => internal name,
     * "" = ignore) when given, else are guessed from the header names. The name
     * column is required.
     *
     * @param array<int, string>|null $mapping
     *
     * @throws CsvImportException
     */
    public function read(string $content, ?array $mapping = null, bool $hasHeader = true): CsvTable
    {
        $table = $this->inspect($content, $hasHeader, $mapping);
        if (!$table->has('name')) {
            throw new CsvImportException(null === $mapping ? 'import.error.no_name_column' : 'import.error.name_not_mapped', ['%headers%' => implode(', ', $table->headers)]);
        }

        return $table;
    }

    /**
     * Reads the file without requiring any column: for the column mapping step.
     * The columns are $mapping, else guessed from header names (and $remembered
     * choices: header slug => internal name).
     *
     * @param array<int, string>|null $mapping
     * @param array<string, string>   $remembered
     *
     * @throws CsvImportException
     */
    public function inspect(string $content, bool $hasHeader = true, ?array $mapping = null, array $remembered = []): CsvTable
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

        $rows = [];
        $line = 0;
        $width = 0;
        $header = null;
        while (false !== ($cells = fgetcsv($stream, null, $delimiter, '"', ''))) {
            ++$line;
            $cells = array_map(static fn ($c): string => (string) $c, $cells);
            if ($hasHeader && null === $header) {
                $header = $cells;
                continue;
            }
            if ([] === array_filter($cells, static fn (string $c): bool => '' !== trim($c))) {
                continue; // empty line
            }
            if (\count($rows) >= self::MAX_ROWS) {
                throw new CsvImportException('import.error.too_many_rows', ['%max%' => self::MAX_ROWS]);
            }
            $width = max($width, \count($cells));
            $rows[] = ['line' => $line, 'cells' => $cells];
        }
        fclose($stream);

        if ($hasHeader && null === $header) {
            throw new CsvImportException('import.error.empty');
        }
        if ([] === $rows) {
            throw new CsvImportException('import.error.no_rows');
        }

        // Without a header row, columns are "Kolumna 1", "Kolumna 2", …
        $headers = null !== $header
            ? array_map(static fn (string $h): string => trim($h), $header)
            : array_map(static fn (int $i): string => 'Kolumna '.($i + 1), range(0, $width - 1));

        $mapping = null === $mapping ? $this->guess($headers, $remembered) : $this->cleanMapping($mapping, \count($headers));

        $columns = [];
        $ignored = [];
        foreach ($headers as $index => $title) {
            $column = $mapping[$index] ?? '';
            if ('' !== $column) {
                $columns[$column] = $index;
            } elseif ('' !== $title) {
                $ignored[] = $title;
            }
        }

        return new CsvTable($columns, $rows, $delimiter, $encoding, $ignored, null !== $header ? \count($header) : 0, $headers, $hasHeader);
    }

    /**
     * Guesses the column of each header from its name (Polish or English, any
     * case, with or without diacritics), else from $remembered choices. Each
     * column is used once.
     *
     * @param list<string>          $headers
     * @param array<string, string> $remembered header slug => internal name
     *
     * @return array<int, string> index => internal name ("" = ignore)
     */
    public function guess(array $headers, array $remembered = []): array
    {
        $mapping = [];
        $used = [];
        foreach ($headers as $index => $title) {
            $key = self::headerKey($title);
            $column = '';
            foreach (self::COLUMNS as $name => $aliases) {
                if (\in_array($key, $aliases, true)) {
                    $column = $name;
                    break;
                }
            }
            if ('' === $column && isset($remembered[$key], self::COLUMNS[$remembered[$key]])) {
                $column = $remembered[$key];
            }
            if ('' !== $column && isset($used[$column])) {
                $column = '';
            }
            $used[$column] = true;
            $mapping[$index] = $column;
        }

        return $mapping;
    }

    /** Header name as compared with the aliases: lowercase ASCII slug. */
    public static function headerKey(string $title): string
    {
        return (new AsciiSlugger())->slug(trim($title))->lower()->toString();
    }

    /**
     * @param array<int, string> $mapping
     *
     * @return array<int, string>
     */
    private function cleanMapping(array $mapping, int $count): array
    {
        $clean = [];
        $used = [];
        foreach ($mapping as $index => $column) {
            if (!\is_int($index) || $index < 0 || $index >= $count || !isset(self::COLUMNS[$column])) {
                continue;
            }
            if (isset($used[$column])) {
                throw new CsvImportException('import.error.duplicate_mapping', ['%column%' => $column]);
            }
            $used[$column] = true;
            $clean[$index] = $column;
        }

        return $clean;
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
