<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Block\Type\GalleryBlock;
use App\Entity\PageBlock;
use PHPUnit\Framework\TestCase;

final class GalleryBlockTest extends TestCase
{
    public function testPhotosAreDealtRoundRobinIntoRows(): void
    {
        self::assertSame([[1, 3, 5], [2, 4, 6]], GalleryBlock::split([1, 2, 3, 4, 5, 6], 2));
        self::assertSame([[1, 4], [2, 5], [3, 6]], GalleryBlock::split([1, 2, 3, 4, 5, 6], 3));
    }

    public function testWithFewPhotosEveryRowGetsThemAll(): void
    {
        self::assertSame([[1, 2, 3], [1, 2, 3]], GalleryBlock::split([1, 2, 3], 2));
        self::assertSame([[], []], GalleryBlock::split([], 2));
    }

    public function testContextPairsPhotosWithCaptionLines(): void
    {
        $context = (new GalleryBlock())->getContext(new PageBlock('gallery'), [
            'images' => ['a.jpg', '', 'b.jpg', 'c.jpg'],
            'captions' => "Obozowisko\r\n\nPatrol",
            'layout' => 'nonsense',
        ]);

        self::assertSame('drift', $context['layout'], 'unknown layouts fall back to drift');
        self::assertSame([
            ['index' => 0, 'file' => 'a.jpg', 'caption' => 'Obozowisko'],
            ['index' => 1, 'file' => 'b.jpg', 'caption' => ''],
            ['index' => 2, 'file' => 'c.jpg', 'caption' => 'Patrol'],
        ], $context['photos']);
        self::assertCount(2, $context['rows']);
    }
}
