<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\OverviewPage;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementCard;
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
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementCard::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class OverviewPageTest extends TestCase
{
    public function testRenderCarriesEveryPartOfTheOverview(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ]),
        ], [new AnalysisProblem('b.php', 'broken')]);
        $page = (new OverviewPage())->render(new ReportSite($catalog), $catalog, new CatalogStatistics($catalog));

        self::assertStringContainsString('<h1>SQL catalog</h1>', $page);
        self::assertStringContainsString('Resolution', $page);
        self::assertStringContainsString('Statement kinds', $page);
        self::assertStringContainsString('external-input', $page);
        self::assertStringContainsString('users', $page);
        self::assertStringContainsString('a.php', $page);
        self::assertStringContainsString('broken', $page);
    }

    public function testCardsCountWhatAReaderLooksAtFirst(): void
    {
        self::assertSame(
            '<div class="cards">'
            . '<div class="card "><p class="card-figure">0</p><p class="card-label">statements</p></div>'
            . '<div class="card card-ok"><p class="card-figure">0</p><p class="card-label">fully resolved</p></div>'
            . '<div class="card card-warn"><p class="card-figure">0</p><p class="card-label">search left open</p></div>'
            . '<div class="card card-danger"><p class="card-figure">0</p>'
            . '<p class="card-label">statements needing attention</p></div></div>',
            (new OverviewPage())->cards(new CatalogStatistics(new Catalog())),
        );
    }

    public function testResolutionsExplainEachWayOfNotBeingDetermined(): void
    {
        $resolutions = (new OverviewPage())->resolutions(new CatalogStatistics(new Catalog()));

        self::assertStringContainsString('The statement text is fully determined.', $resolutions);
        self::assertStringContainsString('The call was found but never examined', $resolutions);
    }

    public function testKindsShowWhatTheStatementsDo(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Delete, TextPattern::fromText('DELETE FROM t'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertStringContainsString(
            '<span class="chip k-delete">DELETE</span>',
            (new OverviewPage())->kinds(new CatalogStatistics($catalog)),
        );
    }

    public function testRulesSayThereIsNothingToReportWhenThereIsNot(): void
    {
        $catalog = new Catalog();

        self::assertStringContainsString(
            'Nothing was reported about any statement.',
            (new OverviewPage())->rules(new ReportSite($catalog), new CatalogStatistics($catalog)),
        );
    }

    public function testRulesLinkToWhatEachOneReported(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'spliced'),
            ]),
        ]);

        self::assertStringContainsString(
            '<a href="findings.html#rule-dynamic-sql">',
            (new OverviewPage())->rules(new ReportSite($catalog), new CatalogStatistics($catalog)),
        );
    }

    public function testTablesAreSilentWhenNoStatementNamesOne(): void
    {
        $catalog = new Catalog();

        self::assertSame('', (new OverviewPage())->tables(new ReportSite($catalog), new CatalogStatistics($catalog)));
    }

    public function testTablesLinkToTheirOwnListing(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['wp_posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertStringContainsString(
            '<a href="tables.html#table-wp-posts">',
            (new OverviewPage())->tables(new ReportSite($catalog), new CatalogStatistics($catalog)),
        );
    }

    public function testFilesAreSilentWhenNothingWasFound(): void
    {
        $catalog = new Catalog();

        self::assertSame('', (new OverviewPage())->files(new ReportSite($catalog), new CatalogStatistics($catalog)));
    }

    public function testFilesLinkToTheStatementsFoundInThem(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertStringContainsString(
            '<a href="statements/page-1.html#file-src-a-php"><code>src/a.php</code></a>',
            (new OverviewPage())->files(new ReportSite($catalog), new CatalogStatistics($catalog)),
        );
    }

    public function testProblemsAreListedOnlyWhenThereAreSome(): void
    {
        $page = new OverviewPage();

        self::assertSame('', $page->problems(new Catalog()));
        self::assertStringContainsString(
            '<tr><td><code>b.php</code></td><td>broken</td></tr>',
            $page->problems(new Catalog([], [new AnalysisProblem('b.php', 'broken')])),
        );
    }

    public function testBarRoleFollowsTheChipItGoesWith(): void
    {
        self::assertSame('bar-ok', (new OverviewPage())->barRole('s-ok'));
    }

    public function testRenderIsWrittenExactly(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromSegments([
                new LiteralText('SELECT id FROM posts WHERE slug = '),
                new TextHole(Origin::External, TypeShape::unknown(), '$_GET["s"]'),
            ]), ['posts'], [new Placeholder('?', 0, null, new ValueDomain('int', [7], true, []))], new CallSite('src/a.php', 4, 'R::find', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ], false, ['R::find', 'R::run']),
            new CatalogEntry('b1', StatementKind::Insert, TextPattern::fromText('INSERT INTO posts (id) VALUES (1)'), ['posts'], [], new CallSite('src/b.php', 9, 'R::add', 'pdo.query'), []),
        ];
        $catalog = new Catalog($entries, [new AnalysisProblem('src/broken.php', 'broken')]);

        $site = new ReportSite($catalog);

        self::assertSame(
            '<h1>SQL catalog</h1><p class="lede">Every statement this source can issue, read back from the calls that receive it. Where the text could no'
                . 't be pinned down, the report says which dependency stopped the search rather than leaving the statement simply unknown.</p><div class="cards'
                . '"><div class="card "><p class="card-figure">2</p><p class="card-label">statements</p></div><div class="card card-ok"><p class="card-figure">'
                . '1</p><p class="card-label">fully resolved</p></div><div class="card card-warn"><p class="card-figure">0</p><p class="card-label">search left'
                . ' open</p></div><div class="card card-danger"><p class="card-figure">1</p><p class="card-label">statements needing attention</p></div></div><'
                . 'h2>Resolution</h2><div class="table-wrap"><table><thead><tr><th class="tight">Resolution</th><th class="num">Statements</th><th class="num">'
                . 'Share</th><th>What it means</th></tr></thead><tbody><tr><td class="tight"><span class="chip s-ok">resolved</span></td><td class="num">1</td>'
                . '<td class="num">50%</td><td>The statement text is fully determined.<span class="bar bar-ok"><span style="--w:50%"></span></span></td></tr><t'
                . 'r><td class="tight"><span class="chip s-danger">external-input</span></td><td class="num">1</td><td class="num">50%</td><td>The values were '
                . 'followed to runtime input, so the text cannot be fixed.<span class="bar bar-danger"><span style="--w:50%"></span></span></td></tr><tr><td cl'
                . 'ass="tight"><span class="chip s-warn">incomplete-model</span></td><td class="num">0</td><td class="num">0%</td><td>A dependency the analyzer'
                . ' does not model was reached.<span class="bar bar-warn"><span style="--w:0%"></span></span></td></tr><tr><td class="tight"><span class="chip '
                . 's-warn">incomplete</span></td><td class="num">0</td><td class="num">0%</td><td>A cycle or an analysis budget stopped the search before it cl'
                . 'osed.<span class="bar bar-warn"><span style="--w:0%"></span></span></td></tr><tr><td class="tight"><span class="chip s-neutral">not-analyzed'
                . '</span></td><td class="num">0</td><td class="num">0%</td><td>The call was found but never examined, so nothing was read from it.<span class='
                . '"bar bar-neutral"><span style="--w:0%"></span></span></td></tr></tbody></table></div><h2>Statement kinds</h2><div class="table-wrap"><table>'
                . '<thead><tr><th class="tight">Kind</th><th class="num">Statements</th><th class="num">Share</th><th>&nbsp;</th></tr></thead><tbody><tr><td cl'
                . 'ass="tight"><span class="chip k-select">SELECT</span></td><td class="num">1</td><td class="num">50%</td><td><span class="bar bar-select"><sp'
                . 'an style="--w:50%"></span></span></td></tr><tr><td class="tight"><span class="chip k-insert">INSERT</span></td><td class="num">1</td><td cla'
                . 'ss="num">50%</td><td><span class="bar bar-insert"><span style="--w:50%"></span></span></td></tr></tbody></table></div><h2>Findings <span cla'
                . 'ss="count">1 finding</span></h2><div class="table-wrap"><table><thead><tr><th>Rule</th><th class="tight">Severity</th><th class="num">Count<'
                . '/th><th>What it reports</th></tr></thead><tbody><tr><td class="tight"><a href="findings.html#rule-external-input"><code>external-input</code'
                . '></a></td><td class="tight"><span class="chip s-danger">high</span></td><td class="num">1</td><td>A value spliced into the statement text co'
                . 'mes from external input.</td></tr></tbody></table></div><h2>Tables <span class="count">1 table</span></h2><p class="lede">The twelve the sta'
                . 'tements name most. <a href="tables.html">See every table</a>.</p><div class="table-wrap"><table><thead><tr><th>Table</th><th class="num">Rea'
                . 'd by</th><th class="num">Written by</th></tr></thead><tbody><tr><td><a href="tables.html#table-posts"><code>posts</code></a></td><td class="'
                . 'num">1</td><td class="num">1</td></tr></tbody></table></div><h2>Files <span class="count">2 files</span></h2><div class="table-wrap"><table>'
                . '<thead><tr><th>File</th><th class="num">Statements</th><th class="num">Left open</th><th class="num">Findings</th></tr></thead><tbody><tr><t'
                . 'd><a href="statements/page-1.html#file-src-a-php"><code>src/a.php</code></a></td><td class="num">1</td><td class="num"><span class="none">0<'
                . '/span></td><td class="num">1</td></tr><tr><td><a href="statements/page-1.html#file-src-b-php"><code>src/b.php</code></a></td><td class="num"'
                . '>1</td><td class="num"><span class="none">0</span></td><td class="num"><span class="none">0</span></td></tr></tbody></table></div><h2>Not re'
                . 'ad <span class="count">1 file</span></h2><p class="lede">These files could not be parsed, so nothing in them was catalogued.</p><div class="'
                . 'table-wrap"><table><thead><tr><th>File</th><th>Why</th></tr></thead><tbody><tr><td><code>src/broken.php</code></td><td>broken</td></tr></tbo'
                . 'dy></table></div>',
            (new OverviewPage())->render($site, $catalog, new CatalogStatistics($catalog)),
        );
    }
}
