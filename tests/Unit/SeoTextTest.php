<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Seo\Text;
use PHPUnit\Framework\TestCase;

final class SeoTextTest extends TestCase
{
    public function testMetaTextIsPlainOneLineAndCutAtAWord(): void
    {
        self::assertNull(Text::meta(null));
        self::assertNull(Text::meta('<p> </p>'));
        self::assertSame('Larp w świecie „Wiedźmina” & co.', Text::meta("<p>Larp w świecie <strong>„Wiedźmina”</strong></p>\n\n<p>&amp; co.</p>"));

        $long = Text::meta(str_repeat('słowo ', 60));
        self::assertNotNull($long);
        self::assertLessThanOrEqual(160, mb_strlen($long));
        self::assertStringEndsWith('słowo…', $long, 'no half words');
    }

    public function testPriceIsReadFromFreeText(): void
    {
        self::assertSame(['amount' => '350', 'currency' => 'PLN'], Text::price('350 zł'));
        self::assertSame(['amount' => '1200.50', 'currency' => 'PLN'], Text::price('od 1 200,50 PLN'));
        self::assertSame(['amount' => '90', 'currency' => 'EUR'], Text::price('90 €'));
        self::assertNull(Text::price('do ustalenia'));
        self::assertNull(Text::price(null));
    }
}
