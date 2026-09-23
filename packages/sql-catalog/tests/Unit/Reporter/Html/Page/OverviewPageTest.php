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
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\OverviewPage;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SqlFormatter;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\StatementRow;
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
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementRow::class)]
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

        self::assertStringContainsString('<h1>Overview</h1>', $page);
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

    public function testRenderIsWrittenExactly(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromSegments([
                new LiteralText('SELECT id FROM posts WHERE slug = '),
                new TextHole(Origin::External, TypeShape::unknown(), '$_GET["s"]'),
            ]), ['posts'], [new Placeholder('?', 0, null, new ValueDomain('int', [7], true, []))], new CallSite('src/a.php', 4, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ], false, ['App\\R::run', 'App\\R::find']),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT INTO posts (id) VALUES (1)'), ['posts'], [], new CallSite('src/a.php', 9, 'App\\R::add', 'pdo.query'), []),
            new CatalogEntry('a3', StatementKind::Update, TextPattern::fromText('UPDATE posts SET title = ? WHERE id = ?'), ['posts'], [], new CallSite('src/a.php', 14, 'App\\R::add', 'pdo.prepare'), [
                Finding::of(FindingRule::PlaceholderCountMismatch, 'one bound'),
            ]),
            new CatalogEntry('b1', StatementKind::Delete, TextPattern::fromText('DELETE FROM users WHERE id = 1'), ['users'], [], new CallSite('src/b.php', 3, 'App\\Admin\\U::drop', 'pdo.query'), []),
            new CatalogEntry('b2', StatementKind::Alter, TextPattern::fromText('ALTER TABLE users ADD x INT'), ['users'], [], new CallSite('src/b.php', 8, 'App\\Admin\\U::migrate', 'pdo.exec'), []),
            new CatalogEntry('c1', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), [], [], new CallSite('lib/c.php', 2, 'helper', 'mysqli.query'), [
                Finding::of(FindingRule::AnalysisIncomplete, 'stopped'),
            ], true, [], true),
            new CatalogEntry('c2', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')), [], [], new CallSite('lib/c.php', 6, '{main}', 'unmatched'), [
                Finding::of(FindingRule::CallNotAnalyzed, 'unseen'),
            ]),
            new CatalogEntry('d1', StatementKind::Select, TextPattern::fromText('SELECT * FROM posts p JOIN users u ON u.id = p.author'), ['posts', 'users'], [], new CallSite('src/d.php', 1, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'dyn'),
            ]),
            new CatalogEntry('e1', StatementKind::Show, TextPattern::fromText('SHOW TABLES'), [], [], new CallSite('src/e.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('f1', StatementKind::Select, TextPattern::fromText('SELECT 1 FROM t1'), ['t1'], [], new CallSite('src/f.php', 1, 'App\\F::a', 'pdo.query'), []),
            new CatalogEntry('f2', StatementKind::Select, TextPattern::fromText('SELECT 2 FROM t2'), ['t2'], [], new CallSite('src/f.php', 2, 'App\\F::b', 'pdo.query'), []),
            new CatalogEntry('f3', StatementKind::Select, TextPattern::fromText('SELECT 3 FROM t3'), ['t3'], [], new CallSite('src/f.php', 3, 'App\\G::a', 'pdo.query'), []),
            new CatalogEntry('f4', StatementKind::Select, TextPattern::fromText('SELECT 4 FROM t4'), ['t4'], [], new CallSite('src/f.php', 4, 'App\\H::a', 'pdo.query'), []),
            new CatalogEntry('f5', StatementKind::Select, TextPattern::fromText('SELECT 5 FROM t5'), ['t5'], [], new CallSite('src/g.php', 1, 'App\\I::a', 'pdo.query'), []),
            new CatalogEntry('f6', StatementKind::Select, TextPattern::fromText('SELECT 6 FROM t6'), ['t6'], [], new CallSite('src/h.php', 1, 'App\\J::a', 'pdo.query'), []),
            new CatalogEntry('f7', StatementKind::Select, TextPattern::fromText('SELECT 7 FROM t7'), ['t7'], [], new CallSite('src/i.php', 1, 'App\\K::a', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'dyn'),
            ]),
            new CatalogEntry('f8', StatementKind::Select, TextPattern::fromText('SELECT 8 FROM t8'), ['t8'], [], new CallSite('src/j.php', 1, 'App\\L::a', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'dyn'),
            ]),
            new CatalogEntry('f9', StatementKind::Select, TextPattern::fromText('SELECT 9 FROM t9'), ['t9'], [], new CallSite('src/k.php', 1, 'App\\M::a', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'dyn'),
            ]),
        ];
        $catalog = new Catalog($entries, [new AnalysisProblem('src/broken.php', 'broken')]);
        $site = new ReportSite($catalog);

        self::assertSame(
            '<h1>Overview</h1><p class="lede">Every statement this source can issue, read back from the calls that receive it. Start from the table, clas'
                . 's or file you are working on, or from what the analysis flagged.</p><p class="facts"><a href="statements.html">18 statements</a><span class='
                . '"facts-sep">·</span><a href="tables.html">11 tables</a><span class="facts-sep">·</span><a href="namespaces.html">15 functions</a><span class'
                . '="facts-sep">·</span><a href="files.html">11 files</a></p><div class="routes"><section class="route"><h2><a href="tables.html">Tables</a><sp'
                . 'an class="count">11</span></h2><p class="route-hint">Which statements read, write or alter a table, and where each is issued. Start here bef'
                . 'ore changing a schema.</p><ol class="route-top"><li><a href="tables/posts.html">posts</a><span class="route-figures">4 · 2 read · 2 write</s'
                . 'pan></li><li><a href="tables/users.html">users</a><span class="route-figures">3 · 1 read · 1 write</span></li><li><a href="tables/t1.html">t'
                . '1</a><span class="route-figures">1 · 1 read · 0 write</span></li><li><a href="tables/t2.html">t2</a><span class="route-figures">1 · 1 read ·'
                . ' 0 write</span></li><li><a href="tables/t3.html">t3</a><span class="route-figures">1 · 1 read · 0 write</span></li><li><a href="tables/t4.ht'
                . 'ml">t4</a><span class="route-figures">1 · 1 read · 0 write</span></li></ol><p class="route-all"><a href="tables.html">All 11 tables</a></p><'
                . '/section><section class="route"><h2><a href="namespaces.html">Namespaces</a><span class="count">3</span></h2><p class="route-hint">The state'
                . 'ments each class and function issues, method by method. Start here before refactoring code that talks to the database.</p><ol class="route-t'
                . 'op"><li><a href="classes/app-r.html">App\\R</a><span class="route-figures">5 statements</span></li><li><a href="classes/app-admin-u.html">App'
                . '\\Admin\\U</a><span class="route-figures">2 statements</span></li><li><a href="classes/app-f.html">App\\F</a><span class="route-figures">2 stat'
                . 'ements</span></li><li><a href="classes/app-g.html">App\\G</a><span class="route-figures">1 statement</span></li><li><a href="classes/app-h.ht'
                . 'ml">App\\H</a><span class="route-figures">1 statement</span></li><li><a href="classes/app-i.html">App\\I</a><span class="route-figures">1 stat'
                . 'ement</span></li></ol><p class="route-all"><a href="namespaces.html">All 3 namespaces</a></p></section><section class="route"><h2><a href="f'
                . 'iles.html">Files</a><span class="count">11</span></h2><p class="route-hint">The statements written in each file, function by function.</p><o'
                . 'l class="route-top"><li><a href="files/src-f-php.html">src/f.php</a><span class="route-figures">4 statements</span></li><li><a href="files/s'
                . 'rc-a-php.html">src/a.php</a><span class="route-figures">3 statements</span></li><li><a href="files/lib-c-php.html">lib/c.php</a><span class='
                . '"route-figures">2 statements</span></li><li><a href="files/src-b-php.html">src/b.php</a><span class="route-figures">2 statements</span></li>'
                . '<li><a href="files/src-d-php.html">src/d.php</a><span class="route-figures">1 statement</span></li><li><a href="files/src-e-php.html">src/e.'
                . 'php</a><span class="route-figures">1 statement</span></li></ol><p class="route-all"><a href="files.html">All 11 files</a></p></section><sect'
                . 'ion class="route"><h2><a href="statements.html">Statements</a><span class="count">18</span></h2><p class="route-hint">Every statement, to na'
                . 'rrow down by what it does, how far the analysis got and what was reported.</p><ol class="route-top route-chips"><li><a class="chip k-select"'
                . ' href="statements.html?kind=select">SELECT</a><span class="route-figures">12</span></li><li><a class="chip k-other" href="statements.html?ki'
                . 'nd=unknown">UNKNOWN</a><span class="route-figures">1</span></li><li><a class="chip k-insert" href="statements.html?kind=insert">INSERT</a><s'
                . 'pan class="route-figures">1</span></li><li><a class="chip k-update" href="statements.html?kind=update">UPDATE</a><span class="route-figures"'
                . '>1</span></li><li><a class="chip k-delete" href="statements.html?kind=delete">DELETE</a><span class="route-figures">1</span></li><li><a clas'
                . 's="chip k-schema" href="statements.html?kind=alter">ALTER</a><span class="route-figures">1</span></li><li><a class="chip k-other" href="stat'
                . 'ements.html?kind=show">SHOW</a><span class="route-figures">1</span></li></ol><p class="route-all"><a href="statements.html">All 18 statement'
                . 's</a></p></section></div><h2 id="attention">Needs attention<span class="count">8 findings</span></h2><div class="split"><div class="table-wr'
                . 'ap"><table><thead><tr><th>Rule</th><th class="tight">Severity</th><th class="num">Statements</th><th>What it reports</th></tr></thead><tbody'
                . '><tr><td class="tight"><a class="mono" href="findings.html#rule-dynamic-sql">dynamic-sql</a></td><td class="tight"><span class="chip s-warn"'
                . '>medium</span></td><td class="num">4</td><td>A value is spliced into the statement text instead of being bound.</td></tr><tr><td class="tigh'
                . 't"><a class="mono" href="findings.html#rule-analysis-incomplete">analysis-incomplete</a></td><td class="tight"><span class="chip s-neutral">'
                . 'low</span></td><td class="num">1</td><td>A cycle or an analysis budget stopped the search before it closed.</td></tr><tr><td class="tight"><'
                . 'a class="mono" href="findings.html#rule-call-not-analyzed">call-not-analyzed</a></td><td class="tight"><span class="chip s-neutral">low</spa'
                . 'n></td><td class="num">1</td><td>A call that carries a statement was found but never examined.</td></tr><tr><td class="tight"><a class="mono'
                . '" href="findings.html#rule-external-input">external-input</a></td><td class="tight"><span class="chip s-danger">high</span></td><td class="n'
                . 'um">1</td><td>A value spliced into the statement text comes from external input.</td></tr><tr><td class="tight"><a class="mono" href="findin'
                . 'gs.html#rule-placeholder-count-mismatch">placeholder-count-mismatch</a></td><td class="tight"><span class="chip s-warn">medium</span></td><t'
                . 'd class="num">1</td><td>The statement binds a different number of values than it has placeholders.</td></tr></tbody></table></div><section c'
                . 'lass="aside"><h3>Functions issuing flagged statements</h3><ol class="route-top"><li><a class="mono" href="findings.html#hotspots">R::find</a'
                . '><span class="route-figures">src/a.php · 1 high · 1 medium</span></li><li><a class="mono" href="findings.html#hotspots">K::a</a><span class='
                . '"route-figures">src/i.php · 1 medium</span></li><li><a class="mono" href="findings.html#hotspots">L::a</a><span class="route-figures">src/j.'
                . 'php · 1 medium</span></li><li><a class="mono" href="findings.html#hotspots">M::a</a><span class="route-figures">src/k.php · 1 medium</span><'
                . '/li><li><a class="mono" href="findings.html#hotspots">R::add</a><span class="route-figures">src/a.php · 1 medium</span></li></ol><p class="r'
                . 'oute-all"><a href="findings.html#hotspots">Every flagged function</a></p></section></div><h2 id="coverage">How far the analysis got</h2><div'
                . ' class="stack"><a class="bar-ok" style="--w:83%" href="statements.html?resolution=resolved" title="resolved: The statement text is fully det'
                . 'ermined."></a><a class="bar-danger" style="--w:6%" href="statements.html?resolution=external-input" title="external-input: The values were f'
                . 'ollowed to runtime input, so the text cannot be fixed."></a><a class="bar-open" style="--w:6%" href="statements.html?resolution=incomplete" '
                . 'title="incomplete: A cycle or an analysis budget stopped the search before it closed."></a><a class="bar-neutral" style="--w:6%" href="state'
                . 'ments.html?resolution=not-analyzed" title="not-analyzed: The call was found but never examined, so nothing was read from it."></a></div><ul '
                . 'class="legend"><li><a class="chip s-ok" href="statements.html?resolution=resolved" title="The statement text is fully determined.">resolved<'
                . '/a><span class="legend-count">15</span><span class="legend-note">The statement text is fully determined.</span></li><li><a class="chip s-dan'
                . 'ger" href="statements.html?resolution=external-input" title="The values were followed to runtime input, so the text cannot be fixed.">extern'
                . 'al-input</a><span class="legend-count">1</span><span class="legend-note">The values were followed to runtime input, so the text cannot be fi'
                . 'xed.</span></li><li><a class="chip s-open" href="statements.html?resolution=incomplete-model" title="A dependency the analyzer does not mode'
                . 'l was reached.">incomplete-model</a><span class="legend-count">0</span><span class="legend-note">A dependency the analyzer does not model wa'
                . 's reached.</span></li><li><a class="chip s-open" href="statements.html?resolution=incomplete" title="A cycle or an analysis budget stopped t'
                . 'he search before it closed.">incomplete</a><span class="legend-count">1</span><span class="legend-note">A cycle or an analysis budget stoppe'
                . 'd the search before it closed.</span></li><li><a class="chip s-neutral" href="statements.html?resolution=not-analyzed" title="The call was f'
                . 'ound but never examined, so nothing was read from it.">not-analyzed</a><span class="legend-count">1</span><span class="legend-note">The call'
                . ' was found but never examined, so nothing was read from it.</span></li></ul><p class="muted"><a href="statements.html?open=open">2 statement'
                . 's</a> are lower bounds: a dependency, a cycle or a budget stopped the search, so the call may issue more than is listed.</p><h2 id="problems'
                . '">Not read<span class="count">1 file</span></h2><p class="lede">These files could not be parsed, so nothing in them was catalogued.</p><div '
                . 'class="table-wrap"><table><thead><tr><th>File</th><th>Why</th></tr></thead><tbody><tr><td><code>src/broken.php</code></td><td>broken</td></t'
                . 'r></tbody></table></div>',
            (new OverviewPage())->render($site),
        );
    }
}
