<?php

declare(strict_types=1);

namespace App\Social;

/** One social media button: network, address and an optional own label (e.g. "Grupa graczy"). */
final readonly class SocialLink
{
    public function __construct(
        public SocialNetwork $network,
        public string $url,
        public ?string $customLabel = null,
    ) {
    }

    /** The own label, or the network's name. */
    public function getLabel(): string
    {
        return $this->customLabel ?? $this->network->label();
    }

    public function getIcon(): string
    {
        return $this->network->icon();
    }

    /**
     * Turns stored rows ({network, url, label}) into links, skipping incomplete
     * or unknown ones.
     *
     * @param array<mixed> $rows
     *
     * @return list<self>
     */
    public static function fromRows(array $rows): array
    {
        $links = [];
        foreach ($rows as $row) {
            if (!\is_array($row) || !\is_string($row['url'] ?? null) || '' === $row['url']) {
                continue;
            }
            $network = SocialNetwork::tryFrom((string) ($row['network'] ?? ''));
            if (null !== $network) {
                $label = \is_string($row['label'] ?? null) && '' !== trim($row['label']) ? trim($row['label']) : null;
                $links[] = new self($network, $row['url'], $label);
            }
        }

        return $links;
    }
}
