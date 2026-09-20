<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\TextPattern;

#[CoversClass(ReportSite::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
final class ReportSiteTest extends TestCase
{
    public function testGroupByFileKeepsAFilesStatementsTogether(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['a.php', 'b.php'], array_keys((new ReportSite($catalog))->groupByFile($catalog)));
    }

    public function testPaginateStartsANewPageRatherThanSplittingAFile(): void
    {
        $catalog = new Catalog();
        $entry = new CatalogEntry('x', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);

        self::assertSame(
            [['a.php' => [$entry, $entry]], ['b.php' => [$entry, $entry]]],
            (new ReportSite($catalog))->paginate(['a.php' => [$entry, $entry], 'b.php' => [$entry, $entry]], 2),
        );
    }

    public function testPaginateKeepsAFileWholeEvenWhenItIsLargerThanAPage(): void
    {
        $catalog = new Catalog();
        $entry = new CatalogEntry('x', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);

        self::assertCount(1, (new ReportSite($catalog))->paginate(['a.php' => [$entry, $entry, $entry]], 2));
    }

    public function testPaginateAlwaysLeavesOnePageToRead(): void
    {
        self::assertSame([[]], (new ReportSite(new Catalog()))->paginate([], 2));
    }

    public function testPagesHoldTheFilesTheySplitInto(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame([['a.php'], ['b.php']], array_map('array_keys', (new ReportSite($catalog, 1))->pages()));
    }

    public function testPageCountSaysHowManyPagesTheStatementsAreSplitAcross(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(2, (new ReportSite($catalog, 1))->pageCount());
        self::assertSame(1, (new ReportSite($catalog))->pageCount());
    }

    public function testPageNameIsWhereAPageOfStatementsIsWritten(): void
    {
        self::assertSame('statements/page-3.html', (new ReportSite(new Catalog()))->pageName(3));
    }

    public function testUrlOfAddressesAStatementOnThePageItIsOn(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
        ]);
        $site = new ReportSite($catalog, 1);

        self::assertSame('statements/page-2.html#b1', $site->urlOf('b1'));
    }

    public function testUrlOfFallsBackToTheOverviewForAStatementItDoesNotHold(): void
    {
        self::assertSame('index.html', (new ReportSite(new Catalog()))->urlOf('missing'));
    }

    public function testFileUrlAddressesTheFilesStatements(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame('statements/page-1.html#file-src-a-php', (new ReportSite($catalog))->fileUrl('src/a.php'));
    }

    public function testFileUrlFallsBackToTheOverviewForAFileItDoesNotHold(): void
    {
        self::assertSame('index.html', (new ReportSite(new Catalog()))->fileUrl('none.php'));
    }

    public function testFileAnchorIsWhatTheFilesStatementsAreGroupedUnder(): void
    {
        self::assertSame('file-src-a-php', (new ReportSite(new Catalog()))->fileAnchor('src/a.php'));
    }

    public function testFilesSayHowManyStatementsEachOneHolds(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['a.php' => 2], (new ReportSite($catalog))->files());
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerPrefixOf(): array
    {
        return [['index.html', ''], ['statements/page-1.html', '../']];
    }

    #[DataProvider('providerPrefixOf')]
    public function testPrefixOfIsWhatALinkFromThatPageGoesThrough(string $page, string $expected): void
    {
        self::assertSame($expected, (new ReportSite(new Catalog()))->prefixOf($page));
    }
}
