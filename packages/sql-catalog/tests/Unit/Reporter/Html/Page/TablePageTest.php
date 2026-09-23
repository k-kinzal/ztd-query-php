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
use SqlCatalog\Reporter\Html\Page\TablePage;
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

#[CoversClass(TablePage::class)]
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
#[UsesClass(TableName::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class TablePageTest extends TestCase
{
    public function testRenderSaysWhatUsesTheTableAndListsItsStatementsByWhatTheyDo(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1 FROM users'), ['users', 'posts'], [], new CallSite('a.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT INTO users'), ['users'], [], new CallSite('a.php', 2, 'helper', 'pdo.query'), []),
        ]);
        $page = (new TablePage())->render(new ReportSite($catalog), 'users');

        self::assertStringContainsString('<h1><code>users</code><span class="count">2 statements</span></h1>', $page);
        self::assertStringContainsString('read by 1 statement, written by 1 statement.', $page);
        self::assertStringContainsString('<h2 id="used-from">Used from<span class="count">2 functions</span></h2>', $page);
        self::assertStringContainsString('<h2 id="alongside">Named alongside</h2>', $page);
        self::assertStringContainsString('<section class="group" id="writes"><h3>Writes<span class="count">1</span></h3>', $page);
        self::assertStringContainsString('<section class="group" id="reads"><h3>Reads<span class="count">1</span></h3>', $page);
        self::assertStringContainsString('href="../statements/a2.html"', $page);
    }

    public function testSummaryMentionsWhatNeedsAttention(): void
    {
        $summary = (new TablePage())->summary(new TableName('{$}'), ['reads' => 2, 'writes' => 0, 'schema' => 1, 'other' => 0, 'attention' => 1]);

        self::assertSame('A table whose name the analysis could not pin down, read by 2 statements, altered by 1 statement. 1 statement carry a finding worth looking at.', $summary);
    }

    public function testUsedFromLinksEachFunctionAndSaysWhatItDoes(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Delete, TextPattern::fromText('DELETE'), ['users'], [], new CallSite('src/a.php', 2, 'App\\R::find', 'pdo.query'), []),
        ];
        $site = new ReportSite(new Catalog($entries));

        self::assertStringContainsString(
            '<tr><td><a class="mono" href="../classes/app-r.html#fn-app-r-find">R::find</a></td><td><a class="muted" href="../files/src-a-php.html">src/a.php</a></td>'
            . '<td class="num">2</td><td class="tight">reads, writes</td></tr>',
            (new TablePage())->usedFrom($site, $entries),
        );
    }

    public function testVerbsNameEveryUseFound(): void
    {
        self::assertSame('reads, alters, other', (new TablePage())->verbs(['reads' => 1, 'writes' => 0, 'schema' => 2, 'other' => 1, 'attention' => 0]));
    }

    public function testAlongsideIsSilentWhenTheTableIsNamedAlone(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame('', (new TablePage())->alongside(new ReportSite($catalog), 'users'));
    }

    public function testGroupsListOnlyTheUsesPresent(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Show, TextPattern::fromText('SHOW TABLES'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ];
        $groups = (new TablePage())->groups(new ReportSite(new Catalog($entries)), 'users', $entries);

        self::assertStringStartsWith('<section class="group" id="other"><h3>Other statements<span class="count">1</span></h3>', $groups);
        self::assertStringNotContainsString('id="reads"', $groups);
    }

    public function testAnchorsFollowTheSectionsPresent(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Update, TextPattern::fromText('UPDATE'), ['users'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            [['Used from', 'used-from'], ['Named alongside', 'alongside'], ['Writes', 'writes'], ['Reads', 'reads']],
            (new TablePage())->anchors(new CatalogIndex($catalog), 'users'),
        );
    }


    public function testSummaryIsSilentAboutFindingsWhenThereAreNone(): void
    {
        self::assertSame(
            'This table is read by 1 statement.',
            (new TablePage())->summary(new TableName('users'), ['reads' => 1, 'writes' => 0, 'schema' => 0, 'other' => 0, 'attention' => 0]),
        );
    }

    public function testContextListsTheSectionsAndEveryOtherTable(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), ['users'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            [
                ['On this page', [['Used from', '#used-from', null, false], ['Named alongside', '#alongside', null, false], ['Reads', '#reads', null, false]], null],
                ['Tables', [['users', 'tables/users.html', 2, false], ['posts', 'tables/posts.html', 1, true]], 'tables.html'],
            ],
            (new TablePage())->context(new ReportSite($catalog), 'posts'),
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
            '<h1><code>posts</code><span class="count">4 statements</span></h1><p class="lede">This table is read by 2 statements, written by 2 statement'
                . 's. 3 statements carry a finding worth looking at.</p><h2 id="used-from">Used from<span class="count">2 functions</span></h2><div class="tabl'
                . 'e-wrap"><table class="sortable"><thead><tr><th data-sort="text">Function</th><th data-sort="text">File</th><th class="num" data-sort="num">S'
                . 'tatements</th><th class="tight">Does</th></tr></thead><tbody><tr><td><a class="mono" href="../classes/app-r.html#fn-app-r-add">R::add</a></t'
                . 'd><td><a class="muted" href="../files/src-a-php.html">src/a.php</a></td><td class="num">2</td><td class="tight">writes</td></tr><tr><td><a c'
                . 'lass="mono" href="../classes/app-r.html#fn-app-r-find">R::find</a></td><td><a class="muted" href="../files/src-a-php.html">src/a.php</a></td'
                . '><td class="num">2</td><td class="tight">reads</td></tr></tbody></table></div><h2 id="alongside">Named alongside</h2><p class="lede">Tables '
                . 'that appear in the same statements, usually through a join.</p><ol class="route-top route-chips"><li><a class="chip chip-ghost" href="../tab'
                . 'les/users.html">users</a><span class="route-figures">1</span></li></ol><h2 id="statements">Statements</h2><div class="filterable" data-narro'
                . 'wable><div class="facets"><input type="search" class="facet-search" placeholder="Narrow by text…" autocomplete="off" spellcheck="false"><spa'
                . 'n class="facet-group"><button type="button" class="chip facet k-select" data-facet="kind" data-value="select">SELECT<span class="facet-count'
                . '">2</span></button><button type="button" class="chip facet k-insert" data-facet="kind" data-value="insert">INSERT<span class="facet-count">1'
                . '</span></button><button type="button" class="chip facet k-update" data-facet="kind" data-value="update">UPDATE<span class="facet-count">1</s'
                . 'pan></button></span><span class="facet-group"><button type="button" class="chip facet s-ok" data-facet="resolution" data-value="resolved">re'
                . 'solved<span class="facet-count">3</span></button><button type="button" class="chip facet s-danger" data-facet="resolution" data-value="exter'
                . 'nal-input">external-input<span class="facet-count">1</span></button></span><span class="facet-group"><button type="button" class="chip facet'
                . ' s-warn" data-facet="severity" data-value="medium">medium<span class="facet-count">2</span></button><button type="button" class="chip facet '
                . 's-danger" data-facet="severity" data-value="high">high<span class="facet-count">1</span></button></span><span class="facet-shown" data-total'
                . '="4"></span><button type="button" class="facet-clear" hidden>Clear</button></div><section class="group" id="writes"><h3>Writes<span class="c'
                . 'ount">2</span></h3><ol class="rows"><li class="row" data-kind="insert" data-resolution="resolved" data-severity="" data-rule="" data-sink="p'
                . 'do.query" data-open="" data-table="posts" data-namespace="App" data-class="App\\R" data-function="App\\R::add" data-file="src/a.php"><a class='
                . '"row-main" href="../statements/a2.html"><span class="chip k-insert">INSERT</span><code class="row-sql"><span class="tok-kw">INSERT</span> <s'
                . 'pan class="tok-kw">INTO</span> posts (id) <span class="tok-kw">VALUES</span> (<span class="tok-num">1</span>)</code></a><div class="row-meta'
                . '"><a class="row-site" href="../files/src-a-php.html">src/a.php:9</a><a class="row-fn" href="../classes/app-r.html#fn-app-r-add">R::add</a><a'
                . ' class="chip chip-ghost" href="../tables/posts.html">posts</a></div></li><li class="row" data-kind="update" data-resolution="resolved" data-'
                . 'severity="medium" data-rule="placeholder-count-mismatch" data-sink="pdo.prepare" data-open="" data-table="posts" data-namespace="App" data-c'
                . 'lass="App\\R" data-function="App\\R::add" data-file="src/a.php"><a class="row-main" href="../statements/a3.html"><span class="chip k-update">U'
                . 'PDATE</span><code class="row-sql"><span class="tok-kw">UPDATE</span> posts <span class="tok-kw">SET</span> title = <span class="tok-ph">?</s'
                . 'pan> <span class="tok-kw">WHERE</span> id = <span class="tok-ph">?</span></code></a><div class="row-meta"><a class="row-site" href="../files'
                . '/src-a-php.html">src/a.php:14</a><a class="row-fn" href="../classes/app-r.html#fn-app-r-add">R::add</a><a class="chip chip-ghost" href="../t'
                . 'ables/posts.html">posts</a><span class="chip s-warn" title="The most serious finding on this statement">medium</span></div></li></ol></secti'
                . 'on><section class="group" id="reads"><h3>Reads<span class="count">2</span></h3><ol class="rows"><li class="row" data-kind="select" data-reso'
                . 'lution="external-input" data-severity="high" data-rule="external-input" data-sink="pdo.query" data-open="" data-table="posts" data-namespace'
                . '="App" data-class="App\\R" data-function="App\\R::find" data-file="src/a.php"><a class="row-main" href="../statements/a1.html"><span class="ch'
                . 'ip k-select">SELECT</span><code class="row-sql"><span class="tok-kw">SELECT</span> id <span class="tok-kw">FROM</span> posts <span class="to'
                . 'k-kw">WHERE</span> slug = <span class="hole hole-external" title="This is a gap: external input fills it. Written as $_GET[&quot;s&quot;].">'
                . '{$}</span></code></a><div class="row-meta"><a class="row-site" href="../files/src-a-php.html">src/a.php:4</a><a class="row-fn" href="../clas'
                . 'ses/app-r.html#fn-app-r-find">R::find</a><a class="chip chip-ghost" href="../tables/posts.html">posts</a><span class="chip s-danger" title="'
                . 'The values were followed to runtime input, so the text cannot be fixed.">external-input</span><span class="chip s-danger" title="The most se'
                . 'rious finding on this statement">high</span></div></li><li class="row" data-kind="select" data-resolution="resolved" data-severity="medium" '
                . 'data-rule="dynamic-sql" data-sink="pdo.query" data-open="" data-table="posts users" data-namespace="App" data-class="App\\R" data-function="A'
                . 'pp\\R::find" data-file="src/d.php"><a class="row-main" href="../statements/d1.html"><span class="chip k-select">SELECT</span><code class="row'
                . '-sql"><span class="tok-kw">SELECT</span> * <span class="tok-kw">FROM</span> posts p <span class="tok-kw">JOIN</span> users u <span class="to'
                . 'k-kw">ON</span> u.id = p.author</code></a><div class="row-meta"><a class="row-site" href="../files/src-d-php.html">src/d.php:1</a><a class="'
                . 'row-fn" href="../classes/app-r.html#fn-app-r-find">R::find</a><a class="chip chip-ghost" href="../tables/posts.html">posts</a><a class="chip'
                . ' chip-ghost" href="../tables/users.html">users</a><span class="chip s-warn" title="The most serious finding on this statement">medium</span>'
                . '</div></li></ol></section></div>',
            (new TablePage())->render($site, 'posts'),
        );
    }
}
