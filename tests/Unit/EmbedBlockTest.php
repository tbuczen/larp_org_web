<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Block\Type\EmbedBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmbedBlockTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function urls(): iterable
    {
        yield 'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'maps embed' => ['https://www.google.com/maps/embed?pb=!1m18', 'https://www.google.com/maps/embed?pb=!1m18'];
        yield 'plain http' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ', null];
        yield 'other site' => ['https://evil.example/embed', null];
        yield 'injected id' => ['https://youtu.be/"><script>', null];
        yield 'maps page (not embed)' => ['https://www.google.com/maps/place/x', null];
    }

    #[DataProvider('urls')]
    public function testToEmbedUrl(string $url, ?string $expected): void
    {
        self::assertSame($expected, EmbedBlock::toEmbedUrl($url));
    }
}
