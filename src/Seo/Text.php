<?php

declare(strict_types=1);

namespace App\Seo;

/** Text helpers for meta tags and structured data. */
final class Text
{
    /**
     * Plain text for a meta description: no HTML, one line, at most $max
     * characters, cut at a word with "…".
     */
    public static function meta(?string $html, int $max = 160): ?string
    {
        if (null === $html) {
            return null;
        }
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</h2>', '</h3>'], ' ', $html)), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ('' === $text) {
            return null;
        }
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');
        if (false !== $space && $space > $max / 2) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, ' ,.;:–-').'…';
    }

    /**
     * The first amount in a free-text price ("350 zł", "od 420,50 PLN") with its
     * currency; null when there's no number. Currency: PLN unless € / EUR is
     * written.
     *
     * @return array{amount: string, currency: string}|null
     */
    public static function price(?string $price): ?array
    {
        if (null === $price || !preg_match('/(\d[\d\s]*(?:[.,]\d{1,2})?)/u', $price, $m)) {
            return null;
        }
        $amount = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim($m[1]));
        $currency = preg_match('/€|eur/iu', $price) ? 'EUR' : 'PLN';

        return ['amount' => $amount, 'currency' => $currency];
    }
}
