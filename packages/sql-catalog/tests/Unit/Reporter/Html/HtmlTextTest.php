<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Reporter\Html\HtmlText;

#[CoversClass(HtmlText::class)]
final class HtmlTextTest extends TestCase
{
    public function testEscapeMakesTextSafeToPlaceInTheDocument(): void
    {
        self::assertSame('&lt;b&gt; &amp; &quot;x&quot;', (new HtmlText())->escape('<b> & "x"'));
    }

    public function testChipCarriesItsLabelAndRole(): void
    {
        self::assertSame('<span class="chip s-ok">resolved</span>', (new HtmlText())->chip('resolved', 's-ok'));
    }

    public function testChipCanCarryATitle(): void
    {
        self::assertSame(
            '<span class="chip" title="why">x</span>',
            (new HtmlText())->chip('x', '', 'why'),
        );
    }

    public function testChipWithoutARoleCarriesNoExtraClass(): void
    {
        self::assertSame('<span class="chip">x</span>', (new HtmlText())->chip('x'));
    }

    public function testNumberSeparatesThousands(): void
    {
        self::assertSame('12,345', (new HtmlText())->number(12345));
    }

    /**
     * @return list<array{int, string}>
     */
    public static function providerPlural(): array
    {
        return [[0, '0 files'], [1, '1 file'], [2, '2 files']];
    }

    #[DataProvider('providerPlural')]
    public function testPluralAgreesWithTheCount(int $value, string $expected): void
    {
        self::assertSame($expected, (new HtmlText())->plural($value, 'file'));
    }

    /**
     * @return list<array{int, int, string}>
     */
    public static function providerPercent(): array
    {
        return [[1, 0, '0%'], [1, 4, '25%'], [3, 4, '75%'], [4, 4, '100%']];
    }

    #[DataProvider('providerPercent')]
    public function testPercentIsAShareOfATotal(int $value, int $total, string $expected): void
    {
        self::assertSame($expected, (new HtmlText())->percent($value, $total));
    }

    public function testBarCarriesTheShareAsACustomProperty(): void
    {
        self::assertSame(
            '<span class="bar bar-ok"><span style="--w:25%"></span></span>',
            (new HtmlText())->bar(1, 4, 'bar-ok'),
        );
    }

    public function testBarWithoutARoleCarriesNoExtraClass(): void
    {
        self::assertSame('<span class="bar"><span style="--w:50%"></span></span>', (new HtmlText())->bar(1, 2));
    }

    public function testSlugTurnsAPathIntoSomethingWritableAsAnIdentifier(): void
    {
        self::assertSame('wp-includes-class-wpdb-php', (new HtmlText())->slug('wp-includes/class-wpdb.php'));
    }

    public function testTruncateShortensLongTextAndCollapsesSpace(): void
    {
        $text = new HtmlText();
        self::assertSame('a b', $text->truncate("a\n  b", 10));
        self::assertSame('abcd…', $text->truncate('abcdefgh', 5));
    }
}
