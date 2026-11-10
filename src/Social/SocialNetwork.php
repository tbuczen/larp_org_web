<?php

declare(strict_types=1);

namespace App\Social;

/**
 * Networks a social link can point to. Adding one is a new case here (and its
 * icon in assets/icons/): links are stored as JSON, so no migration is needed.
 */
enum SocialNetwork: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Discord = 'discord';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Discord => 'Discord',
            self::YouTube => 'YouTube',
            self::TikTok => 'TikTok',
            self::Website => 'WWW',
        };
    }

    /** UX Icons name. */
    public function icon(): string
    {
        return self::Website === $this ? 'lucide:globe' : 'simple-icons:'.$this->value;
    }

    /**
     * Hosts a link to this network may use; empty = any host.
     *
     * @return list<string>
     */
    public function hosts(): array
    {
        return match ($this) {
            self::Facebook => ['facebook.com', 'fb.com', 'fb.me'],
            self::Instagram => ['instagram.com'],
            self::Discord => ['discord.gg', 'discord.com'],
            self::YouTube => ['youtube.com', 'youtu.be'],
            self::TikTok => ['tiktok.com'],
            self::Website => [],
        };
    }

    /** Whether the URL is https and (for a known network) on one of its hosts, subdomains included. */
    public function accepts(string $url): bool
    {
        if ('https' !== parse_url($url, \PHP_URL_SCHEME)) {
            return false;
        }
        $host = parse_url($url, \PHP_URL_HOST);
        if (!\is_string($host) || !str_contains($host, '.')) {
            return false;
        }
        $host = strtolower($host);
        foreach ($this->hosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return [] === $this->hosts();
    }
}
