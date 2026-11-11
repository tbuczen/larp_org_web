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
        $csv = (string) iconv('UTF-8', 'WINDOWS-1250', "WAKAT;Imię postaci;Dostępność;Kolejność;Uwagi\nKapłanka;Mary Ślęzak;zajęta;2;x\n");
        $table = (new CsvReader())->read($csv);

        self::assertSame('Windows-1250', $table->encoding);
        self::assertSame('Kapłanka', $table->value($table->rows[0]['cells'], 'name'));
        self::assertSame('Mary Ślęzak', $table->value($table->rows[0]['cells'], 'character_name'));
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

    public function testAnExplicitMappingReplacesHeaderNames(): void
    {
        $table = (new CsvReader())->read("Kto;Opis krótki;Śmieci\nVex;Hakerka;x\n", [0 => 'name', 1 => 'teaser', 2 => '']);

        self::assertSame(['name' => 0, 'teaser' => 1], $table->columns);
        self::assertSame(['Kto', 'Opis krótki', 'Śmieci'], $table->headers);
        self::assertSame(['Śmieci'], $table->ignoredHeaders);
        self::assertSame('Vex', $table->value($table->rows[0]['cells'], 'name'));
        self::assertSame('teaser', $table->columnAt(1));
        self::assertSame('', $table->columnAt(2));
    }

    public function testMappingErrors(): void
    {
        try {
            (new CsvReader())->read("a;b\nx;y\n", [0 => 'teaser']);
            self::fail('no name column');
        } catch (CsvImportException $e) {
            self::assertSame('import.error.name_not_mapped', $e->getMessage());
        }

        $this->expectExceptionMessage('import.error.duplicate_mapping');
        (new CsvReader())->read("a;b\nx;y\n", [0 => 'name', 1 => 'name']);
    }

    public function testGuessUsesKnownNamesThenRememberedOnesEachOnce(): void
    {
        $reader = new CsvReader();

        self::assertSame(
            [0 => 'name', 1 => '', 2 => 'gameplay', 3 => 'character_name', 4 => ''],
            $reader->guess(['Nazwa', 'Kolor oczu', 'Rozgrywka', 'Imię', 'Rola']),
            '"Imię" is the in-game name; a second vacancy column is not used twice',
        );
        self::assertSame([0 => 'name', 1 => 'teaser'], $reader->guess(['Postać gracza', 'Krótko'], ['postac-gracza' => 'name', 'krotko' => 'teaser']));
    }

    public function testFileWithoutHeaderRow(): void
    {
        $table = (new CsvReader())->inspect("Vex;K\nBolek;M;extra\n", false);

        self::assertSame(['Kolumna 1', 'Kolumna 2', 'Kolumna 3'], $table->headers);
        self::assertFalse($table->hasHeader);
        self::assertCount(2, $table->rows, 'the first line is data');
        self::assertSame(1, $table->rows[0]['line']);
        self::assertSame(['Vex', 'Bolek'], $table->samples(0));
        self::assertSame([], $table->columns, 'nothing to guess from');
    }
}
