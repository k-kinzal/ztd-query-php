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
use SqlCatalog\Reporter\Html\Page\StatementPage;
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

#[CoversClass(StatementPage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(Palette::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(TableName::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(AnalysisProblem::class)]
final class StatementPageTest extends TestCase
{
    public function testRenderCarriesEverythingKnownAboutTheStatement(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT id FROM users WHERE id = ?'), ['users'], [
            new Placeholder('?', 0, null, new ValueDomain('int', [7], true, [])),
        ], new CallSite('src/a.php', 12, 'App\\R::find', 'pdo.prepare'), [Finding::of(FindingRule::DynamicSql, 'spliced')]);
        $other = new CatalogEntry('a2', StatementKind::Delete, TextPattern::fromText('DELETE FROM users'), ['users'], [], new CallSite('src/a.php', 20, 'App\\R::find', 'pdo.query'), []);
        $page = (new StatementPage())->render(new ReportSite(new Catalog([$entry, $other])), $entry);

        self::assertStringContainsString('<h1><span class="chip k-select">SELECT</span><span>on users</span></h1>', $page);
        self::assertStringContainsString('<pre class="sql sql-full"><span class="tok-kw">SELECT</span> id' . "\n" . '<span class="tok-kw">FROM</span> users', $page);
        self::assertStringContainsString('<div class="split"><section><h2 id="facts">About this statement</h2>', $page);
        self::assertStringContainsString('<h2 id="values">Bound values</h2>', $page);
        self::assertStringContainsString('<h2 id="findings">Findings</h2>', $page);
        self::assertStringContainsString('<h2 id="same-function">Also issued by <code>R::find</code><span class="count">1</span></h2>', $page);
        self::assertStringContainsString('<h2 id="same-table-users">Also on <a class="chip chip-ghost" href="../tables/users.html">users</a><span class="count">1</span></h2>', $page);
    }

    public function testRenderLeavesOutTheValuesWhenTheStatementTakesNone(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, '{main}', 'pdo.query'), []);
        $page = (new StatementPage())->render(new ReportSite(new Catalog([$entry])), $entry);

        self::assertStringNotContainsString('class="split"', $page);
        self::assertStringNotContainsString('Bound values', $page);
        self::assertStringNotContainsString('Also issued by', $page);
    }

    public function testTitleNamesTheTablesOrSaysWhyThereAreNone(): void
    {
        $page = new StatementPage();
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');

        self::assertSame('on {$}posts, users', $page->title(new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['{$}posts', 'users'], [], $site, [])));
        self::assertSame('no table named', $page->title(new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], $site, [])));
        self::assertSame('a call nothing was read from', $page->title(new CatalogEntry('a', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown())), [], [], $site, [])));
    }

    public function testWhereLinksEveryPlaceAndNamesThePathTaken(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 12, 'App\\R::find', 'pdo.prepare'), [], true, ['App\\R::run', 'App\\R::find']);

        self::assertSame(
            'Issued at <a class="mono" href="../files/src-a-php.html">src/a.php:12</a> in <a class="mono" href="../classes/app-r.html#fn-app-r-find">App\\R::find</a>'
            . ' through <span class="chip chip-sm" title="The database call that was matched">pdo.prepare</span>, reached by way of <code>App\\R::run → App\\R::find</code>.',
            (new StatementPage())->where(new ReportSite(new Catalog([$entry])), $entry),
        );
    }

    public function testBodyLaysTheStatementOutAndKeepsTheSourceForm(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText("SELECT 1\n  FROM t"), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);
        $body = (new StatementPage())->body($entry);

        self::assertStringContainsString('<button type="button" class="copy" data-copy="sql-text" title="Copy the statement">Copy</button>', $body);
        self::assertStringContainsString('<pre class="sql sql-full"><span class="tok-kw">SELECT</span> <span class="tok-num">1</span>' . "\n" . '<span class="tok-kw">FROM</span> t</pre>', $body);
        self::assertStringContainsString('<textarea id="sql-text" hidden readonly>SELECT 1' . "\n" . '  FROM t</textarea>', $body);
        self::assertStringContainsString('<details class="as-written"><summary>As written in the source</summary>', $body);
    }

    public function testBodyDoesNotDressUpACallNoStatementWasReadFrom(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);

        self::assertSame(
            '<pre class="sql sql-full"><span class="tok-com">-- no statement was read from this call</span>' . "\n" . '$db-&gt;query($sql)</pre>',
            (new StatementPage())->body($entry),
        );
    }

    public function testCaveatsWarnAboutWhatTheStatementDoesNotSay(): void
    {
        $page = new StatementPage();
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $open = new CatalogEntry('a', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), [], [], $site, [], false);
        $cut = new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], $site, [], true, [], true);
        $unread = new CatalogEntry('c', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown())), [], [], $site, []);

        self::assertStringContainsString('may not be all of them.</li><li>Assembled from parts that vary independently', $page->caveats($open));
        self::assertStringContainsString('A limit on loop passes or on callers cut the search short.', $page->caveats($cut));
        self::assertStringContainsString('never examined', $page->caveats($unread));
        self::assertSame('', $page->caveats(new CatalogEntry('d', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], $site, [])));
    }

    public function testFactsLinkTheTablesAndExplainTheReading(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);
        $facts = (new StatementPage())->facts(new ReportSite(new Catalog([$entry])), $entry);

        self::assertStringContainsString('<dt>Resolution</dt><dd><span class="chip s-open">incomplete</span>', $facts);
        self::assertStringContainsString('<dt>Search</dt><dd><span class="muted">left open: the listing for this call is a lower bound</span></dd>', $facts);
        self::assertStringContainsString('<dt>Tables</dt><dd><a class="chip chip-ghost" href="../tables/users.html">users</a> </dd>', $facts);
        self::assertStringContainsString('<dt>Identifier</dt><dd><code>a1</code>', $facts);
    }

    public function testValuesListTheBindParameters(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [
            new Placeholder(':id', 0, 'id', new ValueDomain('string', ['a', 'b'], true, [])),
        ], new CallSite('a.php', 3, 'f', 'pdo.query'), []);

        self::assertStringContainsString('<code>&#039;a&#039;|&#039;b&#039;</code>', (new StatementPage())->values($entry));
        self::assertSame('', (new StatementPage())->values(new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 3, 'f', 'pdo.query'), [])));
    }

    public function testValueRowSaysWhenNothingWasBoundOrPinnedDown(): void
    {
        $page = new StatementPage();

        self::assertSame(
            '<tr><td class="tight"><code>?</code></td><td class="tight"><code>?</code></td><td><span class="none">unbound</span></td></tr>',
            $page->valueRow(new Placeholder('?', 0, null, null)),
        );
        self::assertSame(
            '<tr><td class="tight"><code>?</code></td><td class="tight"><code>string</code></td><td><span class="muted">not pinned down: external</span></td></tr>',
            $page->valueRow(new Placeholder('?', 0, null, new ValueDomain('string', [], false, ['external']))),
        );
    }

    public function testOpenValueSaysSoWhenNothingIsKnownAboutWhereItCameFrom(): void
    {
        self::assertSame('not pinned down', (new StatementPage())->openValue([]));
    }

    public function testFindingsCarryTheirRuleAndSeverity(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 3, 'f', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'spliced')]);

        self::assertSame(
            '<h2 id="findings">Findings</h2><ul class="finding-list"><li><span class="chip s-warn">medium</span><span><code>dynamic-sql</code> spliced</span></li></ul>',
            (new StatementPage())->findings($entry),
        );
        self::assertSame('', (new StatementPage())->findings(new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 3, 'f', 'pdo.query'), [])));
    }

    public function testRelatedPointsAtTheRestWhenThereIsMoreThanItLists(): void
    {
        $entries = array_map(
            static fn (int $n): CatalogEntry => new CatalogEntry('s' . $n, StatementKind::Select, TextPattern::fromText('SELECT ' . $n), ['users'], [], new CallSite('a.php', $n, 'f', 'pdo.query'), []),
            range(1, 10),
        );
        $related = (new StatementPage())->related(new ReportSite(new Catalog($entries)), 'statements/s1.html', $entries[0]);

        self::assertStringContainsString('<a href="../files/a-php.html#fn-f">Every statement of this function</a>', $related);
        self::assertStringContainsString('<a href="../tables/users.html">Every statement on this table</a>', $related);
        self::assertSame(2, substr_count($related, '<ol class="rows">'));
    }



    public function testRelatedListsTheFirstOfTheRestAndOnlyTheFirstTwoTables(): void
    {
        $entries = array_map(
            static fn (int $n): CatalogEntry => new CatalogEntry('s' . $n, StatementKind::Select, TextPattern::fromText('SELECT ' . $n), ['users', 'posts', 'meta'], [], new CallSite('a.php', $n, 'f', 'pdo.query'), []),
            range(1, 10),
        );
        $related = (new StatementPage())->related(new ReportSite(new Catalog($entries)), 'statements/s1.html', $entries[0]);

        self::assertStringContainsString('href="../statements/s2.html"', $related);
        self::assertStringNotContainsString('href="../statements/s10.html"', $related);
        self::assertStringContainsString('id="same-table-users"', $related);
        self::assertStringContainsString('id="same-table-posts"', $related);
        self::assertStringNotContainsString('id="same-table-meta"', $related);
    }

    public function testWhereSaysNothingAboutThePathWhenTheStatementIsReadWhereItIsWritten(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [], true, ['f']);

        self::assertStringEndsWith('pdo.query</span>.', (new StatementPage())->where(new ReportSite(new Catalog([$entry])), $entry));
    }

    public function testContextLeadsToEveryPlaceTheStatementBelongsToAndTheRestOfItsFunction(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 4, 'App\\R::find', 'pdo.query'), []);
        $other = new CatalogEntry('a2', StatementKind::Delete, TextPattern::fromText('DELETE'), [], [], new CallSite('src/a.php', 9, 'App\\R::find', 'pdo.query'), []);

        self::assertSame(
            [
                ['Belongs to', [
                    ['Table users', 'tables/users.html', 1, false],
                    ['Class R', 'classes/app-r.html', 2, false],
                    ['R::find()', 'classes/app-r.html#fn-app-r-find', 2, false],
                    ['File src/a.php', 'files/src-a-php.html', 2, false],
                ], null],
                ['Statements of R::find', [
                    ['SELECT 1', 'statements/a1.html', null, true],
                    ['DELETE', 'statements/a2.html', null, false],
                ], 'classes/app-r.html#fn-app-r-find'],
            ],
            (new StatementPage())->context(new ReportSite(new Catalog([$entry, $other])), $entry),
        );
    }

    public function testContextOfTopLevelCodeLeadsOnlyToItsFile(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('index.php', 4, '{main}', 'pdo.query'), []);

        self::assertSame(
            ['Belongs to', [['File index.php', 'files/index-php.html', 1, false]], null],
            (new StatementPage())->context(new ReportSite(new Catalog([$entry])), $entry)[0],
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
            '<h1><span class="chip k-select">SELECT</span><span>on posts</span></h1><p class="lede">Issued at <a class="mono" href="../files/src-a-php.ht'
                . 'ml">src/a.php:4</a> in <a class="mono" href="../classes/app-r.html#fn-app-r-find">App\\R::find</a> through <span class="chip chip-sm" title="'
                . 'The database call that was matched">pdo.query</span>, reached by way of <code>App\\R::run → App\\R::find</code>.</p><div class="sql-block"><bu'
                . 'tton type="button" class="copy" data-copy="sql-text" title="Copy the statement">Copy</button><pre class="sql sql-full"><span class="tok-kw">'
                . 'SELECT</span> id
<span class="tok-kw">FROM</span> posts
<span class="tok-kw">WHERE</span> slug = <span class="hole hole-external" title="Thi'
                . 's is a gap: external input fills it. Written as $_GET[&quot;s&quot;].">{$}</span></pre><textarea id="sql-text" hidden readonly>SELECT id FRO'
                . 'M posts WHERE slug = {$}</textarea><details class="as-written"><summary>As written in the source</summary><pre class="sql"><span class="tok-'
                . 'kw">SELECT</span> id <span class="tok-kw">FROM</span> posts <span class="tok-kw">WHERE</span> slug = <span class="hole hole-external" title='
                . '"This is a gap: external input fills it. Written as $_GET[&quot;s&quot;].">{$}</span></pre></details></div><div class="notice notice-warn"><'
                . 'ul><li>Assembled from parts that vary independently, so some of the alternatives at this call may be unreachable.</li></ul></div><div class='
                . '"split"><section><h2 id="facts">About this statement</h2><dl class="facts-grid"><div><dt>Resolution</dt><dd><span class="chip s-danger">exte'
                . 'rnal-input</span> <span class="muted">The values were followed to runtime input, so the text cannot be fixed.</span></dd></div><div><dt>Sear'
                . 'ch</dt><dd><span class="muted">closed: every dependency was followed to its end</span></dd></div><div><dt>Tables</dt><dd><a class="chip chip'
                . '-ghost" href="../tables/posts.html">posts</a> </dd></div><div><dt>Kind</dt><dd><span class="chip k-select">SELECT</span></dd></div><div><dt>'
                . 'Identifier</dt><dd><code>a1</code> <span class="muted">stable across runs while the statement is unchanged</span></dd></div></dl></section><'
                . 'section><h2 id="values">Bound values</h2><div class="table-wrap"><table><thead><tr><th class="tight">Parameter</th><th class="tight">Type</t'
                . 'h><th>Bound to</th></tr></thead><tbody><tr><td class="tight"><code>?</code></td><td class="tight"><code>int</code></td><td><code>7</code></t'
                . 'd></tr></tbody></table></div></section></div><h2 id="findings">Findings</h2><ul class="finding-list"><li><span class="chip s-danger">high</s'
                . 'pan><span><code>external-input</code> spliced</span></li></ul><section><h2 id="same-function">Also issued by <code>R::find</code><span class'
                . '="count">2</span></h2><ol class="rows"><li class="row" data-kind="select" data-resolution="resolved" data-severity="medium" data-rule="dynam'
                . 'ic-sql" data-sink="pdo.query" data-open="" data-table="posts users" data-namespace="App" data-class="App\\R" data-function="App\\R::find" data'
                . '-file="src/d.php"><a class="row-main" href="../statements/d1.html"><span class="chip k-select">SELECT</span><code class="row-sql"><span clas'
                . 's="tok-kw">SELECT</span> * <span class="tok-kw">FROM</span> posts p <span class="tok-kw">JOIN</span> users u <span class="tok-kw">ON</span> '
                . 'u.id = p.author</code></a><div class="row-meta"><a class="row-site" href="../files/src-d-php.html">src/d.php:1</a><a class="chip chip-ghost"'
                . ' href="../tables/posts.html">posts</a><a class="chip chip-ghost" href="../tables/users.html">users</a><span class="chip s-warn" title="The m'
                . 'ost serious finding on this statement">medium</span></div></li><li class="row" data-kind="show" data-resolution="resolved" data-severity="" '
                . 'data-rule="" data-sink="pdo.query" data-open="" data-table="" data-namespace="App" data-class="App\\R" data-function="App\\R::find" data-file='
                . '"src/e.php"><a class="row-main" href="../statements/e1.html"><span class="chip k-other">SHOW</span><code class="row-sql"><span class="tok-kw'
                . '">SHOW</span> TABLES</code></a><div class="row-meta"><a class="row-site" href="../files/src-e-php.html">src/e.php:1</a></div></li></ol></sec'
                . 'tion><section><h2 id="same-table-posts">Also on <a class="chip chip-ghost" href="../tables/posts.html">posts</a><span class="count">3</span>'
                . '</h2><ol class="rows"><li class="row" data-kind="insert" data-resolution="resolved" data-severity="" data-rule="" data-sink="pdo.query" data'
                . '-open="" data-table="posts" data-namespace="App" data-class="App\\R" data-function="App\\R::add" data-file="src/a.php"><a class="row-main" hre'
                . 'f="../statements/a2.html"><span class="chip k-insert">INSERT</span><code class="row-sql"><span class="tok-kw">INSERT</span> <span class="tok'
                . '-kw">INTO</span> posts (id) <span class="tok-kw">VALUES</span> (<span class="tok-num">1</span>)</code></a><div class="row-meta"><a class="ro'
                . 'w-site" href="../files/src-a-php.html">src/a.php:9</a><a class="row-fn" href="../classes/app-r.html#fn-app-r-add">R::add</a><a class="chip c'
                . 'hip-ghost" href="../tables/posts.html">posts</a></div></li><li class="row" data-kind="update" data-resolution="resolved" data-severity="medi'
                . 'um" data-rule="placeholder-count-mismatch" data-sink="pdo.prepare" data-open="" data-table="posts" data-namespace="App" data-class="App\\R" d'
                . 'ata-function="App\\R::add" data-file="src/a.php"><a class="row-main" href="../statements/a3.html"><span class="chip k-update">UPDATE</span><c'
                . 'ode class="row-sql"><span class="tok-kw">UPDATE</span> posts <span class="tok-kw">SET</span> title = <span class="tok-ph">?</span> <span cla'
                . 'ss="tok-kw">WHERE</span> id = <span class="tok-ph">?</span></code></a><div class="row-meta"><a class="row-site" href="../files/src-a-php.htm'
                . 'l">src/a.php:14</a><a class="row-fn" href="../classes/app-r.html#fn-app-r-add">R::add</a><a class="chip chip-ghost" href="../tables/posts.ht'
                . 'ml">posts</a><span class="chip s-warn" title="The most serious finding on this statement">medium</span></div></li><li class="row" data-kind='
                . '"select" data-resolution="resolved" data-severity="medium" data-rule="dynamic-sql" data-sink="pdo.query" data-open="" data-table="posts user'
                . 's" data-namespace="App" data-class="App\\R" data-function="App\\R::find" data-file="src/d.php"><a class="row-main" href="../statements/d1.html'
                . '"><span class="chip k-select">SELECT</span><code class="row-sql"><span class="tok-kw">SELECT</span> * <span class="tok-kw">FROM</span> posts'
                . ' p <span class="tok-kw">JOIN</span> users u <span class="tok-kw">ON</span> u.id = p.author</code></a><div class="row-meta"><a class="row-sit'
                . 'e" href="../files/src-d-php.html">src/d.php:1</a><a class="row-fn" href="../classes/app-r.html#fn-app-r-find">R::find</a><a class="chip chip'
                . '-ghost" href="../tables/posts.html">posts</a><a class="chip chip-ghost" href="../tables/users.html">users</a><span class="chip s-warn" title'
                . '="The most serious finding on this statement">medium</span></div></li></ol></section>',
            (new StatementPage())->render($site, $entries[0]),
        );
    }

    public function testRenderOfACallNothingWasReadFromIsWrittenExactly(): void
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
            '<h1><span class="chip k-other">UNKNOWN</span><span>a call nothing was read from</span></h1><p class="lede">Issued at <a class="mono" href=".'
                . './files/lib-c-php.html">lib/c.php:6</a> through <span class="chip chip-sm" title="The database call that was matched">unmatched</span>.</p><'
                . 'pre class="sql sql-full"><span class="tok-com">-- no statement was read from this call</span>
$db-&gt;query($sql)</pre><div class="notice no'
                . 'tice-warn"><ul><li>The call was found but never examined, so nothing was read from it.</li></ul></div><section><h2 id="facts">About this sta'
                . 'tement</h2><dl class="facts-grid"><div><dt>Resolution</dt><dd><span class="chip s-neutral">not-analyzed</span> <span class="muted">The call '
                . 'was found but never examined, so nothing was read from it.</span></dd></div><div><dt>Search</dt><dd><span class="muted">left open: the listi'
                . 'ng for this call is a lower bound</span></dd></div><div><dt>Tables</dt><dd><span class="none">none named</span></dd></div><div><dt>Kind</dt>'
                . '<dd><span class="chip k-other">UNKNOWN</span></dd></div><div><dt>Identifier</dt><dd><code>c2</code> <span class="muted">stable across runs w'
                . 'hile the statement is unchanged</span></dd></div></dl></section><h2 id="findings">Findings</h2><ul class="finding-list"><li><span class="chi'
                . 'p s-neutral">low</span><span><code>call-not-analyzed</code> unseen</span></li></ul>',
            (new StatementPage())->render($site, $entries[6]),
        );
    }
}
