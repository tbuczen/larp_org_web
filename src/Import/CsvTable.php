<?php

declare(strict_types=1);

namespace App\Import;

/**
 * A parsed CSV file: known columns (by internal name) and the data rows.
 */
final readonly class CsvTable
{
    /**
     * @param array<string, int>                          $columns        internal column name => index
     * @param list<array{line: int, cells: list<string>}> $rows
     * @param list<string>                                $ignoredHeaders headers that matched no known column
     * @param list<string>                                $headers        all column names (or "Kolumna n" without a header row)
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public string $delimiter,
        public string $encoding,
        public array $ignoredHeaders = [],
        public int $headerCount = 0,
        public array $headers = [],
        public bool $hasHeader = true,
    ) {
    }

    /** The internal column a file column is mapped to ("" = ignored). */
    public function columnAt(int $index): string
    {
        return (string) (array_search($index, $this->columns, true) ?: '');
    }

    /**
     * Up to $count example values of a file column, for the mapping screen.
     *
     * @return list<string>
     */
    public function samples(int $index, int $count = 3): array
    {
        $samples = [];
        foreach ($this->rows as $row) {
            $value = trim($row['cells'][$index] ?? '');
            if ('' !== $value) {
                $samples[] = mb_strimwidth($value, 0, 60, '…');
            }
            if (\count($samples) >= $count) {
                break;
            }
        }

        return $samples;
    }

    public function has(string $column): bool
    {
        return isset($this->columns[$column]);
    }

    /** @param list<string> $cells */
    public function value(array $cells, string $column): ?string
    {
        if (!isset($this->columns[$column])) {
            return null;
        }

        return trim($cells[$this->columns[$column]] ?? '');
    }
}
