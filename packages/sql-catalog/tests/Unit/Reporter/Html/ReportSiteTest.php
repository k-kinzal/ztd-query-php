<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;

#[CoversClass(ReportSite::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(Scope::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class ReportSiteTest extends TestCase
{
    public function testCatalogIsHeldInReportingOrder(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['a', 'b'], array_column((new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->catalog()->entries(), 'id'));
    }

    public function testIndexGroupsTheCatalog(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['users'], array_keys((new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->index()->byTable()));
    }

    public function testStatisticsCountTheCatalog(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(1, (new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->statistics()->statements());
    }

    public function testPagesForKeepsNamesApartWhenTheySlugAlike(): void
    {
        self::assertSame(
            ['wp_posts' => 'tables/wp-posts.html', 'wp-posts' => 'tables/wp-posts-2.html', '' => 'tables/unnamed.html'],
            (new ReportSite(new Catalog(), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->pagesFor(['wp_posts', 'wp-posts', ''], 'tables/'),
        );
    }

    public function testStatementPageIsNamedAfterTheIdentifier(): void
    {
        self::assertSame('statements/abc123.html', (new ReportSite(new Catalog(), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->statementPage('abc123'));
    }

    public function testTablePageFallsBackToTheListingForAnUnknownTable(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['app.users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $site = new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter());

        self::assertSame('tables/app-users.html', $site->tablePage('app.users'));
        self::assertSame('tables.html', $site->tablePage('none'));
    }

    public function testClassPageFallsBackToTheListingForAnUnknownClass(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\Users::find', 'pdo.query'), []),
        ]);
        $site = new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter());

        self::assertSame('classes/app-users.html', $site->classPage('App\\Users'));
        self::assertSame('namespaces.html', $site->classPage('None'));
    }

    public function testFilePageFallsBackToTheListingForAnUnknownFile(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $site = new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter());

        self::assertSame('files/src-a-php.html', $site->filePage('src/a.php'));
        self::assertSame('files.html', $site->filePage('none.php'));
    }

    public function testFunctionAnchorIsWhatAFunctionsStatementsAreGroupedUnder(): void
    {
        self::assertSame('fn-app-users-find', (new ReportSite(new Catalog(), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->functionAnchor('\\App\\Users::find'));
    }

    public function testFunctionUrlLeadsToTheClassPageForAMethodAndTheFilePageOtherwise(): void
    {
        $method = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'App\\Users::find', 'pdo.query'), []);
        $function = new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('src/b.php', 1, 'helper', 'pdo.query'), []);
        $site = new ReportSite(new Catalog([$method, $function]), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter());

        self::assertSame('classes/app-users.html#fn-app-users-find', $site->functionUrl($method));
        self::assertSame('files/src-b-php.html#fn-helper', $site->functionUrl($function));
    }

    public function testTablesAreMostNamedFirst(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), ['posts'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['posts', 'users'], (new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->tables());
    }

    public function testClassesAreInNameOrder(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'B::f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'A::f', 'pdo.query'), []),
        ]);

        self::assertSame(['A', 'B'], (new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->classes());
    }

    public function testFilesAreInPathOrder(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['a.php', 'b.php'], (new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->files());
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerPrefixOf(): array
    {
        return [['index.html', ''], ['tables/users.html', '../']];
    }

    #[DataProvider('providerPrefixOf')]
    public function testPrefixOfIsWhatALinkFromThatPageGoesThrough(string $page, string $expected): void
    {
        self::assertSame($expected, (new ReportSite(new Catalog(), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()))->prefixOf($page));
    }
}
