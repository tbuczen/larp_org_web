<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Larp;
use App\Theme\LarpColors;
use PHPUnit\Framework\TestCase;

final class LarpColorsTest extends TestCase
{
    public function testNoColoursKeepTheTheme(): void
    {
        $larp = new Larp();
        self::assertNull(LarpColors::colorsOf($larp));
        self::assertSame([], LarpColors::tokensOf($larp));
        self::assertSame('', LarpColors::css($larp));
        self::assertFalse($larp->hasColors());
    }

    public function testPresetGivesAFullConsistentSetOfTokens(): void
    {
        $larp = (new Larp())->setColorPreset('cyberpunk');
        $tokens = LarpColors::tokensOf($larp);

        self::assertSame('#07070f', $tokens['--color-bg']);
        self::assertSame('#e8f0ff', $tokens['--color-text']);
        self::assertSame('#ff3fd2', $tokens['--color-primary']);
        self::assertSame('#11111f', $tokens['--color-surface'], 'secondary colour = surfaces');
        self::assertSame('#22e5ff', $tokens['--color-accent']);
        self::assertSame('dark', $tokens['color-scheme']);
        self::assertSame('0', $tokens['--logo-invert']);
        foreach (['--color-surface-2', '--color-border', '--color-muted', '--color-on-primary', '--color-link', '--cta-bg'] as $derived) {
            self::assertArrayHasKey($derived, $tokens);
        }
        self::assertStringStartsWith('--color-bg: #07070f; color-scheme: dark;', LarpColors::css($larp));
    }

    public function testOwnColoursReplaceThePresetsAndLightBackgroundsFlipLogos(): void
    {
        $larp = (new Larp())->setColorPreset('cyberpunk')->setColorBackground('#F2E8D3')->setColorText('#222');
        $tokens = LarpColors::tokensOf($larp);

        self::assertSame('#f2e8d3', $tokens['--color-bg'], 'normalized to lowercase');
        self::assertSame('#222222', $tokens['--color-text'], 'short form expanded');
        self::assertSame('#ff3fd2', $tokens['--color-primary'], 'the rest still from the preset');
        self::assertSame('light', $tokens['color-scheme']);
        self::assertSame('1', $tokens['--logo-invert']);

        // Only one own colour, no preset: only that token changes.
        self::assertSame(['--color-accent' => '#c0ffee'], LarpColors::tokensOf((new Larp())->setColorAccent('#c0ffee')));
    }

    public function testContrastMaths(): void
    {
        self::assertEqualsWithDelta(21.0, LarpColors::contrast('#000000', '#ffffff'), .01);
        self::assertEqualsWithDelta(1.0, LarpColors::contrast('#777777', '#777777'), .01);
        self::assertSame('#0a0a0a', LarpColors::readableOn('#ff3fd2'), 'dark text on bright pink');
        self::assertSame('#ffffff', LarpColors::readableOn('#7d2b1c'), 'white text on dark red');
        self::assertSame('#808080', LarpColors::mix('#000000', '#ffffff', .5016));
        self::assertNull(LarpColors::normalize('zielony'));
    }

    public function testEveryPresetIsReadable(): void
    {
        foreach (array_keys(LarpColors::PRESETS) as $key) {
            self::assertSame([], LarpColors::contrastProblems((new Larp())->setColorPreset($key)), $key);
        }
    }

    public function testLowContrastIsReported(): void
    {
        $larp = (new Larp())->setColorPreset('postapo')->setColorText('#3a3028');
        $problems = LarpColors::contrastProblems($larp);

        self::assertCount(1, $problems);
        self::assertSame('text', $problems[0]['pair']);
        self::assertLessThan(4.5, $problems[0]['ratio']);
    }
}
