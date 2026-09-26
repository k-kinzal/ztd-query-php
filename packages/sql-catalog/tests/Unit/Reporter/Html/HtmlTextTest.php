<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Reporter\Html\HtmlText;

#[CoversClass(HtmlText::class)]
final class HtmlTextTest extends TestCase
{
    public function testEscapeMakesTextSafeForTheDocument(): void
    {
        self::assertSame('&lt;a href=&quot;x&quot;&gt;&#039;', (new HtmlText())->escape('<a href="x">\''));
    }

    public function testChipCarriesItsRoleAndTitle(): void
    {
        self::assertSame('<span class="chip tone-blue" title="What it does">SELECT</span>', (new HtmlText())->chip('SELECT', 'tone-blue', 'What it does'));
        self::assertSame('<span class="chip">plain</span>', (new HtmlText())->chip('plain'));
    }

    public function testChipLinkIsAChipThatLeadsSomewhere(): void
    {
        self::assertSame(
            '<a class="chip chip-ghost" href="tables/posts.html" title="t">posts</a>',
            (new HtmlText())->chipLink('posts', 'tables/posts.html', 'chip-ghost', 't'),
        );
    }

    public function testLinkEscapesItsTextAndAddress(): void
    {
        self::assertSame('<a class="mono" href="a.html?q=1&amp;b=2">A &amp; B</a>', (new HtmlText())->link('A & B', 'a.html?q=1&b=2', 'mono'));
        self::assertSame('<a href="a.html">A</a>', (new HtmlText())->link('A', 'a.html'));
    }

    public function testNumberIsWrittenForScanning(): void
    {
        self::assertSame('1,234', (new HtmlText())->number(1234));
    }

    public function testPluralAgreesWithTheCount(): void
    {
        self::assertSame('1 table', (new HtmlText())->plural(1, 'table'));
        self::assertSame('2 tables', (new HtmlText())->plural(2, 'table'));
    }

    public function testPercentIsWholeAndSafeOnZero(): void
    {
        self::assertSame('33%', (new HtmlText())->percent(1, 3));
        self::assertSame('0%', (new HtmlText())->percent(1, 0));
    }

    public function testChipCountCarriesHowManyTheChipStandsFor(): void
    {
        self::assertSame(
            '<a class="chip chip-ghost" href="tables/posts.html">posts<span class="facet-count">1,234</span></a>',
            (new HtmlText())->chipCount('posts', 'tables/posts.html', 1234, 'chip-ghost'),
        );
        self::assertSame('<a class="chip" href="a.html">A &amp; B<span class="facet-count">0</span></a>', (new HtmlText())->chipCount('A & B', 'a.html', 0));
    }

    public function testNounAgreesWithTheCount(): void
    {
        self::assertSame('table', (new HtmlText())->noun(1, 'table'));
        self::assertSame('tables', (new HtmlText())->noun(0, 'table'));
    }

    public function testSlugIsWritableAsAnIdentifier(): void
    {
        self::assertSame('app-users-find', (new HtmlText())->slug('App\\Users::find'));
    }

    public function testCollapseWritesEveryRunOfWhitespaceAsOneSpace(): void
    {
        self::assertSame('a b c', (new HtmlText())->collapse("  a\n\t b   c "));
    }

    public function testTruncateCollapsesAndCuts(): void
    {
        self::assertSame('SELECT…', (new HtmlText())->truncate("SELECT   1\nFROM t", 7));
        self::assertSame('SELECT 1', (new HtmlText())->truncate('SELECT 1', 8));
    }

    public function testMarkedMarksEveryGapInAName(): void
    {
        self::assertSame(
            '<span class="hole tone-warn" title="A part of this name the analysis could not pin down">{$}</span>posts &amp; more',
            (new HtmlText())->marked('{$}posts & more'),
        );
    }

    public function testCountIsWrittenBesideAHeading(): void
    {
        self::assertSame('<span class="count">3 tables</span>', (new HtmlText())->count(3, 'table'));
        self::assertSame('<span class="count">3</span>', (new HtmlText())->count(3));
    }
}
