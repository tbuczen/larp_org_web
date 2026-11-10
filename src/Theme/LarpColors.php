<?php

declare(strict_types=1);

namespace App\Theme;

use App\Entity\Larp;

/**
 * A LARP's own colour scheme, turned into the theme's design tokens
 * (--color-bg, --color-text, …). Themes use tokens only, so this works with any
 * theme. A preset gives the base colours; each colour set on the LARP replaces
 * the preset's; with neither, the theme's own colours stay.
 *
 * Derived tokens (surfaces, borders, muted text, text on buttons) are computed
 * here so the scheme stays consistent and readable.
 */
final class LarpColors
{
    /** Preset key => [label, background, text, primary, secondary, accent]. */
    public const PRESETS = [
        'dark_fantasy' => ['Dark fantasy', '#120d0b', '#ecdcc4', '#c9a24a', '#1d1512', '#b8432f'],
        'frost' => ['Mroźna północ', '#0b1116', '#e5edf2', '#a9cde3', '#131c23', '#d9b25e'],
        'cyberpunk' => ['Cyberpunk neon', '#07070f', '#e8f0ff', '#ff3fd2', '#11111f', '#22e5ff'],
        'postapo' => ['Postapo rdza', '#14100c', '#e9ddc8', '#d0743a', '#1e1813', '#a6b83c'],
        'parchment' => ['Historyczny pergamin', '#f2e8d3', '#2b2118', '#7d2b1c', '#e8dbbe', '#2f5d50'],
    ];

    /** @return list<string> */
    public static function presetKeys(): array
    {
        return array_keys(self::PRESETS);
    }

    /** Fields on Larp, in the order of PRESETS' colour columns. */
    public const FIELDS = ['background', 'text', 'primary', 'secondary', 'accent'];

    /**
     * The LARP's colours (preset + own), or null when it has none.
     *
     * @return array<string, string>|null field => #rrggbb
     */
    public static function colorsOf(Larp $larp): ?array
    {
        $preset = self::PRESETS[$larp->getColorPreset() ?? ''] ?? null;
        $colors = [];
        foreach (self::FIELDS as $i => $field) {
            $value = $larp->getColor($field) ?? (null !== $preset ? $preset[$i + 1] : null);
            if (null !== $value) {
                $colors[$field] = $value;
            }
        }

        return [] === $colors ? null : $colors;
    }

    /**
     * CSS custom properties for the LARP, e.g. ['--color-bg' => '#120d0b', …].
     * Only tokens that the LARP changes are returned.
     *
     * @return array<string, string>
     */
    public static function tokensOf(Larp $larp): array
    {
        $c = self::colorsOf($larp);
        if (null === $c) {
            return [];
        }
        $tokens = [];
        $bg = $c['background'] ?? null;
        $text = $c['text'] ?? null;

        if (null !== $bg) {
            $tokens['--color-bg'] = $bg;
            $light = self::luminance($bg) > .4;
            $tokens['color-scheme'] = $light ? 'light' : 'dark';
            // 1 on light backgrounds: themes can flip light-on-dark logo images (filter: invert()).
            $tokens['--logo-invert'] = $light ? '1' : '0';
        }
        if (null !== $text) {
            $tokens['--color-text'] = $text;
            $tokens['--color-link'] = $c['primary'] ?? $text;
        }
        if (null !== $bg && null !== $text) {
            $tokens['--color-surface'] = $c['secondary'] ?? self::mix($bg, $text, .05);
            $tokens['--color-surface-2'] = self::mix($tokens['--color-surface'], $text, .07);
            $tokens['--color-border'] = self::mix($bg, $text, .2);
            $tokens['--color-muted'] = self::mix($text, $bg, .32);
        } elseif (isset($c['secondary'])) {
            $tokens['--color-surface'] = $c['secondary'];
        }
        if (isset($c['primary'])) {
            $tokens['--color-primary'] = $c['primary'];
            $tokens['--color-on-primary'] = self::readableOn($c['primary']);
            $tokens['--cta-bg'] = $c['primary'];
        }
        if (isset($c['accent'])) {
            $tokens['--color-accent'] = $c['accent'];
        }

        return $tokens;
    }

    /** "prop: value; …" for a style attribute or a CSS rule. */
    public static function css(Larp $larp): string
    {
        $out = [];
        foreach (self::tokensOf($larp) as $property => $value) {
            $out[] = $property.': '.$value;
        }

        return implode('; ', $out);
    }

    /**
     * Readability problems of the LARP's colours (WCAG): body text needs 4.5:1
     * against the background, buttons 3:1.
     *
     * @return list<array{pair: string, ratio: float, min: float}>
     */
    public static function contrastProblems(Larp $larp): array
    {
        $c = self::colorsOf($larp) ?? [];
        $problems = [];
        foreach ([['text', 'background', 4.5], ['primary', 'background', 3.0], ['accent', 'background', 3.0]] as [$fg, $bg, $min]) {
            if (isset($c[$fg], $c[$bg])) {
                $ratio = self::contrast($c[$fg], $c[$bg]);
                if ($ratio < $min) {
                    $problems[] = ['pair' => $fg, 'ratio' => round($ratio, 2), 'min' => $min];
                }
            }
        }

        return $problems;
    }

    /** WCAG contrast ratio, 1–21. */
    public static function contrast(string $a, string $b): float
    {
        [$la, $lb] = [self::luminance($a), self::luminance($b)];

        return (max($la, $lb) + .05) / (min($la, $lb) + .05);
    }

    /** Relative luminance (WCAG). */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(static function (int $v): float {
            $c = $v / 255;

            return $c <= .03928 ? $c / 12.92 : (($c + .055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return .2126 * $r + .7152 * $g + .0722 * $b;
    }

    /** Near-black or white, whichever reads better on the colour. */
    public static function readableOn(string $hex): string
    {
        return self::contrast($hex, '#0a0a0a') >= self::contrast($hex, '#ffffff') ? '#0a0a0a' : '#ffffff';
    }

    /** $a moved $amount (0–1) of the way to $b. */
    public static function mix(string $a, string $b, float $amount): string
    {
        $ca = self::rgb($a);
        $cb = self::rgb($b);
        $out = '#';
        foreach ([0, 1, 2] as $i) {
            $out .= sprintf('%02x', (int) round($ca[$i] + ($cb[$i] - $ca[$i]) * $amount));
        }

        return $out;
    }

    /** "#abc" / "abcdef" / "#ABCDEF" → "#abcdef"; null when it isn't a colour. */
    public static function normalize(?string $value): ?string
    {
        $value = strtolower(ltrim(trim((string) $value), '#'));
        if (1 === preg_match('/^[0-9a-f]{3}$/', $value)) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        return 1 === preg_match('/^[0-9a-f]{6}$/', $value) ? '#'.$value : null;
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
