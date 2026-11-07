<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Import\CsvImportException;
use App\Import\CsvReader;
use PHPUnit\Framework\TestCase;

final class CsvReaderTest extends TestCase
{
    public function testPolishExcelSemicolonsWithBom(): void
    {
        $table = (new CsvReader())->read("\xEF\xBB\xBFNazwa;Zajawka;Obsada;Rozgrywka;Triggery\r\nJarl;\"Władca; twierdzy\";M;Polityka 50%;Zdrada 60%\r\n\r\n");

        self::assertSame(';', $table->delimiter);
        self::assertSame('UTF-8', $table->encoding);
        self::assertSame(['name' => 0, 'teaser' => 1, 'casting' => 2, 'gameplay' => 3, 'triggers' => 4], $table->columns);
        self::assertCount(1, $table->rows, 'empty lines are skipped');
        self::assertSame('Władca; twierdzy', $table->value($table->rows[0]['cells'], 'teaser'));
        self::assertSame(2, $table->rows[0]['line']);
    }

    public function testCommaSeparatedEnglishHeadersAndMultilineCells(): void
    {
        $table = (new CsvReader())->read("name,description,gameplay\nKenji,\"First line\nsecond line\",\"Combat 50, Drama 50\"\n");

        self::assertSame(',', $table->delimiter);
        self::assertSame("First line\nsecond line", $table->value($table->rows[0]['cells'], 'description'));
        self::assertSame('Combat 50, Drama 50', $table->value($table->rows[0]['cells'], 'gameplay'));
    }

    public function testWindows1250IsConvertedAndHeadersMatchWithoutDiacritics(): void
    {
        $csv = (string) iconv('UTF-8', 'WINDOWS-1250', "IMIĘ;Dostępność;Kolejność;Uwagi\nKapłanka Mary;zajęta;2;x\n");
        $table = (new CsvReader())->read($csv);

        self::assertSame('Windows-1250', $table->encoding);
        self::assertSame('Kapłanka Mary', $table->value($table->rows[0]['cells'], 'name'));
        self::assertSame('zajęta', $table->value($table->rows[0]['cells'], 'availability'));
        self::assertSame(['Uwagi'], $table->ignoredHeaders);
    }

    public function testTabSeparated(): void
    {
        $table = (new CsvReader())->read("nazwa\tobsada\nA\tK\n");

        self::assertSame("\t", $table->delimiter);
    }

    public function testFileWithoutNameColumnIsRejected(): void
    {
        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('import.error.no_name_column');

        (new CsvReader())->read("zajawka;obsada\nx;k\n");
    }

    public function testEmptyFileIsRejected(): void
    {
        $this->expectExceptionMessage('import.error.no_rows');

        (new CsvReader())->read("nazwa;obsada\n;\n");
    }
}
