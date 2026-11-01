<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights;

#[CoversClass(GeneralWeights::class)]
#[Small]
final class GeneralWeightsTest extends TestCase
{
    public function testKeyComparesCaseAndAccentVariantsWithoutTrimmingSpaces(): void
    {
        $weights = new GeneralWeights();

        self::assertSame($weights->key('Résumé'), $weights->key('resume'));
        self::assertNotSame($weights->key('x'), $weights->key('x '));
        self::assertSame('', $weights->key(''));
        self::assertSame("A\xff", $weights->key("a\xff"));
    }

    public function testPointUsesSampledPagesAndIdentityWeights(): void
    {
        $weights = new GeneralWeights();

        self::assertSame("\x00E", $weights->point(233));
        self::assertSame("\x73\x2b", $weights->point(29483));
        self::assertSame("\xff\xfd", $weights->point(128512));
    }

    public function testPagesCachesCheckedBinaryPages(): void
    {
        $weights = new GeneralWeights();
        $pages = $weights->pages();

        self::assertNotEmpty($pages);
        self::assertSame(512, strlen($pages[0]));
        self::assertSame($pages, $weights->pages());
    }
}
