<?php

declare(strict_types=1);

namespace App\Block;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * All block types, by key. Filled automatically from classes implementing BlockTypeInterface.
 */
final class BlockTypeRegistry
{
    /** @var array<string, BlockTypeInterface> */
    private array $types = [];

    /** @param iterable<BlockTypeInterface> $types */
    public function __construct(#[AutowireIterator(BlockTypeInterface::TAG)] iterable $types)
    {
        foreach ($types as $type) {
            $this->types[$type::getKey()] = $type;
        }
    }

    /** @return array<string, BlockTypeInterface> */
    public function all(): array
    {
        return $this->types;
    }

    public function get(string $key): ?BlockTypeInterface
    {
        return $this->types[$key] ?? null;
    }
}
