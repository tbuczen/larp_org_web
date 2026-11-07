<?php

declare(strict_types=1);

namespace App\Import;

/** A problem with the whole file (not a single row). The message is a translation key (domain "admin"). */
final class CsvImportException extends \RuntimeException
{
    /** @param array<string, string|int> $parameters */
    public function __construct(string $message, public readonly array $parameters = [])
    {
        parent::__construct($message);
    }
}
