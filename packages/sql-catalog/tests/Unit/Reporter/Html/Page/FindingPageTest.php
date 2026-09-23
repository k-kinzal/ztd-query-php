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
use SqlCatalog\Reporter\Html\Page\FindingPage;
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

#[CoversClass(FindingPage::class)]
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
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(TableName::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class FindingPageTest extends TestCase
{
    public function testRenderOpensWithWhereToLookAndThenListsEveryRule(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
                Finding::of(FindingRule::AnalysisIncomplete, 'stopped'),
            ]),
        ]);
        $page = (new FindingPage())->render(new ReportSite($catalog));

        self::assertStringContainsString('<h1>Findings<span class="count">2 findings</span></h1>', $page);
        self::assertStringContainsString('<h2 id="hotspots">Where to look first</h2>', $page);
        self::assertStringContainsString('<section class="group" id="rule-external-input">', $page);
        self::assertStringContainsString('<section class="group" id="rule-analysis-incomplete">', $page);
        self::assertStringContainsString('Nothing was reported', (new FindingPage())->render(new ReportSite(new Catalog())));
    }

    public function testHotspotsLinkEachFunctionAndAreSilentWhenNothingIsFlagged(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'spliced'),
            ]),
        ]);

        self::assertStringContainsString(
            '<tr><td><a class="mono" href="classes/app-r.html#fn-app-r-find">R::find</a></td><td><a class="muted" href="files/src-a-php.html">src/a.php</a></td>'
            . '<td class="num"><span class="none">0</span></td><td class="num">1</td></tr>',
            (new FindingPage())->hotspots(new ReportSite($catalog)),
        );
        self::assertSame('', (new FindingPage())->hotspots(new ReportSite(new Catalog())));
    }

    public function testSectionHeadsTheRuleWithItsSeverityAndMeaning(): void
    {
        $section = (new FindingPage())->section(new ReportSite(new Catalog()), FindingRule::DynamicSql, []);

        self::assertStringStartsWith(
            '<section class="group" id="rule-dynamic-sql"><h2><code>dynamic-sql</code><span class="chip s-warn">medium</span><span class="count">0 statements</span>',
            $section,
        );
        self::assertStringContainsString('A value is spliced into the statement text instead of being bound.', $section);
    }

    public function testAnchorsNameTheHotspotsAndEveryRule(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'spliced'),
            ]),
        ]);

        self::assertSame([['Where to look first', 'hotspots'], ['dynamic-sql', 'rule-dynamic-sql']], (new FindingPage())->anchors(new ReportSite($catalog)));
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
            '<h1>Findings<span class="count">8 findings</span></h1><p class="lede">A finding is a judgement about a statement the analyzer read: a value '
                . 'spliced into the text, a value that comes from outside the program, or a search that stopped short. What stopped the analysis is reported to'
                . 'o, so a gap in the catalog is never silent.</p><h2 id="hotspots">Where to look first</h2><p class="lede">The functions issuing statements wi'
                . 'th a high or medium finding: SQL built from external input, or from values spliced into the text rather than bound. A function high on this '
                . 'list is one to read before trusting its queries.</p><div class="table-wrap"><table class="sortable"><thead><tr><th data-sort="text">Function'
                . '</th><th data-sort="text">File</th><th class="num" data-sort="num">High</th><th class="num" data-sort="num">Medium</th></tr></thead><tbody><'
                . 'tr><td><a class="mono" href="classes/app-r.html#fn-app-r-find">R::find</a></td><td><a class="muted" href="files/src-a-php.html">src/a.php</a'
                . '></td><td class="num">1</td><td class="num">1</td></tr><tr><td><a class="mono" href="classes/app-k.html#fn-app-k-a">K::a</a></td><td><a clas'
                . 's="muted" href="files/src-i-php.html">src/i.php</a></td><td class="num"><span class="none">0</span></td><td class="num">1</td></tr><tr><td><'
                . 'a class="mono" href="classes/app-l.html#fn-app-l-a">L::a</a></td><td><a class="muted" href="files/src-j-php.html">src/j.php</a></td><td clas'
                . 's="num"><span class="none">0</span></td><td class="num">1</td></tr><tr><td><a class="mono" href="classes/app-m.html#fn-app-m-a">M::a</a></td'
                . '><td><a class="muted" href="files/src-k-php.html">src/k.php</a></td><td class="num"><span class="none">0</span></td><td class="num">1</td></'
                . 'tr><tr><td><a class="mono" href="classes/app-r.html#fn-app-r-add">R::add</a></td><td><a class="muted" href="files/src-a-php.html">src/a.php<'
                . '/a></td><td class="num"><span class="none">0</span></td><td class="num">1</td></tr></tbody></table></div><section class="group" id="rule-dyn'
                . 'amic-sql"><h2><code>dynamic-sql</code><span class="chip s-warn">medium</span><span class="count">4 statements</span><a class="anchor" href="'
                . '#rule-dynamic-sql">#</a></h2><p class="lede">A value is spliced into the statement text instead of being bound.</p><ol class="rows"><li clas'
                . 's="row" data-kind="select" data-resolution="resolved" data-severity="medium" data-rule="dynamic-sql" data-sink="pdo.query" data-open="" data'
                . '-table="posts users" data-namespace="App" data-class="App\\R" data-function="App\\R::find" data-file="src/d.php"><a class="row-main" href="sta'
                . 'tements/d1.html"><span class="chip k-select">SELECT</span><code class="row-sql"><span class="tok-kw">SELECT</span> * <span class="tok-kw">FR'
                . 'OM</span> posts p <span class="tok-kw">JOIN</span> users u <span class="tok-kw">ON</span> u.id = p.author</code></a><div class="row-meta"><a'
                . ' class="row-site" href="files/src-d-php.html">src/d.php:1</a><a class="row-fn" href="classes/app-r.html#fn-app-r-find">R::find</a><a class="'
                . 'chip chip-ghost" href="tables/posts.html">posts</a><a class="chip chip-ghost" href="tables/users.html">users</a><span class="chip s-warn" ti'
                . 'tle="The most serious finding on this statement">medium</span></div></li><li class="row" data-kind="select" data-resolution="resolved" data-'
                . 'severity="medium" data-rule="dynamic-sql" data-sink="pdo.query" data-open="" data-table="t7" data-namespace="App" data-class="App\\K" data-fu'
                . 'nction="App\\K::a" data-file="src/i.php"><a class="row-main" href="statements/f7.html"><span class="chip k-select">SELECT</span><code class="'
                . 'row-sql"><span class="tok-kw">SELECT</span> <span class="tok-num">7</span> <span class="tok-kw">FROM</span> t7</code></a><div class="row-met'
                . 'a"><a class="row-site" href="files/src-i-php.html">src/i.php:1</a><a class="row-fn" href="classes/app-k.html#fn-app-k-a">K::a</a><a class="c'
                . 'hip chip-ghost" href="tables/t7.html">t7</a><span class="chip s-warn" title="The most serious finding on this statement">medium</span></div>'
                . '</li><li class="row" data-kind="select" data-resolution="resolved" data-severity="medium" data-rule="dynamic-sql" data-sink="pdo.query" data'
                . '-open="" data-table="t8" data-namespace="App" data-class="App\\L" data-function="App\\L::a" data-file="src/j.php"><a class="row-main" href="st'
                . 'atements/f8.html"><span class="chip k-select">SELECT</span><code class="row-sql"><span class="tok-kw">SELECT</span> <span class="tok-num">8<'
                . '/span> <span class="tok-kw">FROM</span> t8</code></a><div class="row-meta"><a class="row-site" href="files/src-j-php.html">src/j.php:1</a><a'
                . ' class="row-fn" href="classes/app-l.html#fn-app-l-a">L::a</a><a class="chip chip-ghost" href="tables/t8.html">t8</a><span class="chip s-warn'
                . '" title="The most serious finding on this statement">medium</span></div></li><li class="row" data-kind="select" data-resolution="resolved" d'
                . 'ata-severity="medium" data-rule="dynamic-sql" data-sink="pdo.query" data-open="" data-table="t9" data-namespace="App" data-class="App\\M" dat'
                . 'a-function="App\\M::a" data-file="src/k.php"><a class="row-main" href="statements/f9.html"><span class="chip k-select">SELECT</span><code cla'
                . 'ss="row-sql"><span class="tok-kw">SELECT</span> <span class="tok-num">9</span> <span class="tok-kw">FROM</span> t9</code></a><div class="row'
                . '-meta"><a class="row-site" href="files/src-k-php.html">src/k.php:1</a><a class="row-fn" href="classes/app-m.html#fn-app-m-a">M::a</a><a clas'
                . 's="chip chip-ghost" href="tables/t9.html">t9</a><span class="chip s-warn" title="The most serious finding on this statement">medium</span></'
                . 'div></li></ol></section><section class="group" id="rule-analysis-incomplete"><h2><code>analysis-incomplete</code><span class="chip s-neutral'
                . '">low</span><span class="count">1 statement</span><a class="anchor" href="#rule-analysis-incomplete">#</a></h2><p class="lede">A cycle or an'
                . ' analysis budget stopped the search before it closed.</p><ol class="rows"><li class="row" data-kind="select" data-resolution="incomplete" da'
                . 'ta-severity="low" data-rule="analysis-incomplete" data-sink="mysqli.query" data-open="open" data-table="" data-namespace="" data-class="" da'
                . 'ta-function="helper" data-file="lib/c.php"><a class="row-main" href="statements/c1.html"><span class="chip k-select">SELECT</span><code clas'
                . 's="row-sql"><span class="hole hole-open" title="This is a gap: a dependency the analyzer stopped following fills it.">{$}</span></code></a><'
                . 'div class="row-meta"><a class="row-site" href="files/lib-c-php.html">lib/c.php:2</a><a class="row-fn" href="files/lib-c-php.html#fn-helper">'
                . 'helper</a><span class="chip s-open" title="A cycle or an analysis budget stopped the search before it closed.">incomplete</span></div></li><'
                . '/ol></section><section class="group" id="rule-call-not-analyzed"><h2><code>call-not-analyzed</code><span class="chip s-neutral">low</span><s'
                . 'pan class="count">1 statement</span><a class="anchor" href="#rule-call-not-analyzed">#</a></h2><p class="lede">A call that carries a stateme'
                . 'nt was found but never examined.</p><ol class="rows"><li class="row" data-kind="unknown" data-resolution="not-analyzed" data-severity="low" '
                . 'data-rule="call-not-analyzed" data-sink="unmatched" data-open="open" data-table="" data-namespace="" data-class="" data-function="{main}" da'
                . 'ta-file="lib/c.php"><a class="row-main" href="statements/c2.html"><span class="chip k-other">UNKNOWN</span><code class="row-sql"><span class'
                . '="tok-com">no statement was read from this call</span> $db-&gt;query($sql)</code></a><div class="row-meta"><a class="row-site" href="files/l'
                . 'ib-c-php.html">lib/c.php:6</a><span class="chip s-neutral" title="The call was found but never examined, so nothing was read from it.">not-a'
                . 'nalyzed</span></div></li></ol></section><section class="group" id="rule-external-input"><h2><code>external-input</code><span class="chip s-d'
                . 'anger">high</span><span class="count">1 statement</span><a class="anchor" href="#rule-external-input">#</a></h2><p class="lede">A value spli'
                . 'ced into the statement text comes from external input.</p><ol class="rows"><li class="row" data-kind="select" data-resolution="external-inpu'
                . 't" data-severity="high" data-rule="external-input" data-sink="pdo.query" data-open="" data-table="posts" data-namespace="App" data-class="Ap'
                . 'p\\R" data-function="App\\R::find" data-file="src/a.php"><a class="row-main" href="statements/a1.html"><span class="chip k-select">SELECT</spa'
                . 'n><code class="row-sql"><span class="tok-kw">SELECT</span> id <span class="tok-kw">FROM</span> posts <span class="tok-kw">WHERE</span> slug '
                . '= <span class="hole hole-external" title="This is a gap: external input fills it. Written as $_GET[&quot;s&quot;].">{$}</span></code></a><di'
                . 'v class="row-meta"><a class="row-site" href="files/src-a-php.html">src/a.php:4</a><a class="row-fn" href="classes/app-r.html#fn-app-r-find">'
                . 'R::find</a><a class="chip chip-ghost" href="tables/posts.html">posts</a><span class="chip s-danger" title="The values were followed to runti'
                . 'me input, so the text cannot be fixed.">external-input</span><span class="chip s-danger" title="The most serious finding on this statement">'
                . 'high</span></div></li></ol></section><section class="group" id="rule-placeholder-count-mismatch"><h2><code>placeholder-count-mismatch</code>'
                . '<span class="chip s-warn">medium</span><span class="count">1 statement</span><a class="anchor" href="#rule-placeholder-count-mismatch">#</a>'
                . '</h2><p class="lede">The statement binds a different number of values than it has placeholders.</p><ol class="rows"><li class="row" data-kin'
                . 'd="update" data-resolution="resolved" data-severity="medium" data-rule="placeholder-count-mismatch" data-sink="pdo.prepare" data-open="" dat'
                . 'a-table="posts" data-namespace="App" data-class="App\\R" data-function="App\\R::add" data-file="src/a.php"><a class="row-main" href="statement'
                . 's/a3.html"><span class="chip k-update">UPDATE</span><code class="row-sql"><span class="tok-kw">UPDATE</span> posts <span class="tok-kw">SET<'
                . '/span> title = <span class="tok-ph">?</span> <span class="tok-kw">WHERE</span> id = <span class="tok-ph">?</span></code></a><div class="row-'
                . 'meta"><a class="row-site" href="files/src-a-php.html">src/a.php:14</a><a class="row-fn" href="classes/app-r.html#fn-app-r-add">R::add</a><a '
                . 'class="chip chip-ghost" href="tables/posts.html">posts</a><span class="chip s-warn" title="The most serious finding on this statement">mediu'
                . 'm</span></div></li></ol></section>',
            (new FindingPage())->render($site),
        );
    }
}
