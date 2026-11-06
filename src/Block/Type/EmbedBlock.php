<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use App\Entity\PageBlock;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A YouTube video or a Google Maps map. Only these two providers are allowed, so an
 * admin can't embed arbitrary third-party pages.
 */
final class EmbedBlock extends AbstractBlockType
{
    public static function getKey(): string
    {
        return 'embed';
    }

    public function getIcon(): string
    {
        return 'fa fa-film';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'url' => '', 'caption' => ''];
    }

    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false])
            ->add('url', UrlType::class, ['label' => 'block.embed.url', 'help' => 'block.embed.url_help', 'default_protocol' => 'https'])
            ->add('caption', TextType::class, ['label' => 'block.embed.caption', 'required' => false]);
    }

    public function getContext(PageBlock $block, array $settings): array
    {
        return ['embed_url' => self::toEmbedUrl((string) ($settings['url'] ?? ''))];
    }

    /** Turns a YouTube or Google Maps link into an embeddable URL; null for anything else. */
    public static function toEmbedUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (false === $parts || 'https' !== ($parts['scheme'] ?? null) || !isset($parts['host'])) {
            return null;
        }
        $host = preg_replace('/^www\./', '', strtolower($parts['host']));
        parse_str($parts['query'] ?? '', $query);

        $videoId = match ($host) {
            'youtube.com', 'm.youtube.com' => match (true) {
                isset($query['v']) && \is_string($query['v']) => $query['v'],
                1 === preg_match('#^/(?:embed|shorts|live)/([^/?]+)#', $parts['path'] ?? '', $m) => $m[1],
                default => null,
            },
            'youtu.be' => ltrim($parts['path'] ?? '', '/'),
            default => null,
        };
        if (null !== $videoId) {
            return 1 === preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId) ? 'https://www.youtube-nocookie.com/embed/'.$videoId : null;
        }

        if ('google.com' === $host && str_starts_with($parts['path'] ?? '', '/maps/embed')) {
            return $url;
        }

        return null;
    }
}
