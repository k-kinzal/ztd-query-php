<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\OverviewPage;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\TableName;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(OverviewPage::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(Palette::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(TableName::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class OverviewPageTest extends TestCase
{
    public function testRenderLaysOutEveryRouteToAStatement(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'spliced'),
            ]),
        ], [new AnalysisProblem('b.php', 'broken')]);
        $page = (new OverviewPage())->render(new ReportSite($catalog));

        self::assertStringContainsString('<h1>SQL catalog</h1>', $page);
        self::assertStringContainsString('<div class="routes">', $page);
        self::assertStringContainsString('href="tables/users.html"', $page);
        self::assertStringContainsString('href="classes/app-r.html"', $page);
        self::assertStringContainsString('href="files/src-a-php.html"', $page);
        self::assertStringContainsString('href="statements.html?kind=select"', $page);
        self::assertStringContainsString('Needs attention', $page);
        self::assertStringContainsString('How far the analysis got', $page);
        self::assertStringContainsString('broken', $page);
    }

    public function testFactsLinkEachCountToItsRoute(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            '<p class="facts"><a href="statements.html">1 statement</a><span class="facts-sep">·</span><a href="tables.html">1 table</a>'
            . '<span class="facts-sep">·</span><a href="namespaces.html">1 function</a><span class="facts-sep">·</span><a href="files.html">1 file</a></p>',
            (new OverviewPage())->facts(new ReportSite($catalog)),
        );
    }

    public function testTableRouteListsTheMostNamedTablesWithHowTheyAreUsed(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT'), ['users'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);
        $route = (new OverviewPage())->tableRoute(new ReportSite($catalog));

        self::assertStringContainsString('<li><a href="tables/users.html">users</a><span class="route-figures">2 · 1 read · 1 write</span></li>', $route);
        self::assertStringContainsString('<a href="tables.html">All 1 table</a>', $route);
    }

    public function testNamespaceRouteListsClassesOrFallsBackToFunctions(): void
    {
        $withClass = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\R::find', 'pdo.query'), []),
        ]);
        $withoutClass = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'helper', 'pdo.query'), []),
        ]);
        $page = new OverviewPage();

        self::assertStringContainsString('<a href="classes/app-r.html">App\\R</a>', $page->namespaceRoute(new ReportSite($withClass)));
        self::assertStringContainsString('<a href="statements.html?function=helper">helper</a>', $page->namespaceRoute(new ReportSite($withoutClass)));
    }

    public function testFileRouteListsTheFilesWithTheMostStatements(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b2', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('b.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertStringContainsString(
            '<li><a href="files/b-php.html">b.php</a><span class="route-figures">2 statements</span></li><li><a href="files/a-php.html">a.php</a>',
            (new OverviewPage())->fileRoute(new ReportSite($catalog)),
        );
    }

    public function testKindRouteLeadsToTheListingNarrowedByKind(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Delete, TextPattern::fromText('DELETE FROM t'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertStringContainsString(
            '<a class="chip k-delete" href="statements.html?kind=delete">DELETE</a><span class="route-figures">1</span>',
            (new OverviewPage())->kindRoute(new ReportSite($catalog)),
        );
    }

    public function testRouteSaysSoWhenThereIsNothingOnIt(): void
    {
        self::assertSame(
            '<section class="route"><h2><a href="tables.html">Tables</a><span class="count">0</span></h2><p class="route-hint">hint</p>'
            . '<p class="none">Nothing here.</p><p class="route-all"><a href="tables.html">All 0 tables</a></p></section>',
            (new OverviewPage())->route('Tables', 'tables.html', 0, 'table', 'hint', ''),
        );
    }

    public function testAttentionListsRulesAndTheFunctionsFlaggedMost(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ]),
        ]);
        $attention = (new OverviewPage())->attention(new ReportSite($catalog));

        self::assertStringContainsString('<a class="mono" href="findings.html#rule-external-input">external-input</a>', $attention);
        self::assertStringContainsString('<a class="mono" href="findings.html#hotspots">f</a><span class="route-figures">a.php · 1 high</span>', $attention);
        self::assertStringContainsString('Nothing was reported', (new OverviewPage())->attention(new ReportSite(new Catalog())));
    }

    public function testCoverageLeadsFromEverySegmentToTheStatementsItCounts(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);
        $coverage = (new OverviewPage())->coverage(new ReportSite($catalog));

        self::assertStringContainsString('<a class="bar-ok" style="--w:50%" href="statements.html?resolution=resolved"', $coverage);
        self::assertStringNotContainsString('class="bar-neutral"', $coverage);
        self::assertStringContainsString('<a href="statements.html?open=open">1 statement</a> are lower bounds', $coverage);
        self::assertStringContainsString('Every search closed', (new OverviewPage())->coverage(new ReportSite(new Catalog())));
    }

    public function testProblemsAreListedOnlyWhenThereAreSome(): void
    {
        $page = new OverviewPage();

        self::assertSame('', $page->problems(new ReportSite(new Catalog())));
        self::assertStringContainsString(
            '<tr><td><code>b.php</code></td><td>broken</td></tr>',
            $page->problems(new ReportSite(new Catalog([], [new AnalysisProblem('b.php', 'broken')]))),
        );
    }
}
