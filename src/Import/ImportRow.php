<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Character;
use App\Enum\CharacterAvailability;
use App\Enum\CharacterCasting;

/**
 * One CSV row after checking: what will happen to it and with which values.
 */
final class ImportRow
{
    public const CREATE = 'create';
    public const UPDATE = 'update';
    public const SKIP = 'skip';
    public const ERROR = 'error';

    public string $action = self::CREATE;

    /** @var list<array{0: string, 1: array<string, string|int>}> translation key + parameters (domain "admin") */
    public array $errors = [];

    /** @var list<string> tag names this row will create for the LARP */
    public array $newTags = [];

    public ?Character $existing = null;
    public string $slug = '';
    public ?string $teaser = null;
    public ?string $description = null;
    public ?CharacterCasting $casting = null;
    public ?CharacterAvailability $availability = null;
    public ?int $position = null;

    /** @var array<string, list<array{name: string, percentage: int}>> tag type value => tags (only for columns present) */
    public array $tags = [];

    public function __construct(public readonly int $line, public string $name)
    {
    }

    /** @param array<string, string|int> $parameters */
    public function error(string $key, array $parameters = []): void
    {
        $this->errors[] = [$key, $parameters];
        $this->action = self::ERROR;
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }
}
