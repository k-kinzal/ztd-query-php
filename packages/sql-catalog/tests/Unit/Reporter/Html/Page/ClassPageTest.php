<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\AnalysisProblem;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Catalog\Finding;
use SqlCatalog\Core\Catalog\FindingRule;
use SqlCatalog\Core\Catalog\Placeholder;
use SqlCatalog\Core\Catalog\Resolution;
use SqlCatalog\Core\Catalog\Severity;
use SqlCatalog\Core\Catalog\StatementPart;
use SqlCatalog\Core\Catalog\ValueDomain;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\ClassPage;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SqlFormatter;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\StatementRow;
use SqlCatalog\Reporter\Html\TableName;

#[CoversClass(ClassPage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
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
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class ClassPageTest extends TestCase
{
    public function testRenderListsTheStatementsMethodByMethod(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 9, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT'), ['users'], [], new CallSite('src/a.php', 3, 'App\\R::add', 'pdo.query'), []),
        ]);
        $page = (new ClassPage())->render(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), 'App\\R');

        self::assertStringContainsString('<h1><code>R</code><span class="count">2 statements</span></h1>', $page);
        self::assertStringContainsString('2 statements in 2 methods of <code>App\\R</code>, written in <a href="../files/src-a-php.html">src/a.php</a>.', $page);
        self::assertStringContainsString('<a class="chip chip-ghost" href="../tables/users.html">users<span class="facet-count">2</span></a>', $page);
        self::assertMatchesRegularExpression('/id="fn-app-r-add".*id="fn-app-r-find"/s', $page);
    }

    public function testTablesSaySoWhenNoneIsNamed(): void
    {
        self::assertSame('<h2 id="tables">Tables</h2><p class="empty-inline">No statement here names a table.</p>', (new ClassPage())->tables(new ReportSite(new Catalog(), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), []));
    }

    public function testMethodsAreInTheOrderTheyAreWritten(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 9, 'R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 3, 'R::add', 'pdo.query'), []),
        ];

        self::assertSame(['R::add', 'R::find'], array_keys((new ClassPage())->methods($entries)));
    }

    public function testSectionsHeadEachMethodWithWhereItStarts(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 9, 'R::find', 'pdo.query'), []);
        $site = new ReportSite(new Catalog([$entry]), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter());

        self::assertStringStartsWith(
            '<section class="group" id="fn-r-find"><h3><code>R::find</code><span class="count">1</span><span class="muted">a.php:9</span><a class="anchor" href="#fn-r-find">#</a></h3><ol class="rows">',
            (new ClassPage())->sections($site, 'classes/r.html', ['R::find' => [$entry]]),
        );
    }

    public function testAnchorsNameTheTablesAndEveryMethod(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 9, 'R::find', 'pdo.query'), []);

        self::assertSame([['Tables', 'tables'], ['find', 'fn-r-find']], (new ClassPage())->anchors(new ReportSite(new Catalog([$entry]), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), [$entry]));
    }


    public function testContextListsTheMethodsAndTheClassesOfTheSameNamespace(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'App\\S::go', 'pdo.query'), []),
            new CatalogEntry('a3', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('a.php', 3, 'Other\\T::go', 'pdo.query'), []),
        ]);

        self::assertSame(
            [
                ['On this page', [['Tables', '#tables', null, false], ['find', '#fn-app-r-find', null, false]], null],
                ['Classes in App', [['R', 'classes/app-r.html', 1, true], ['S', 'classes/app-s.html', 1, false]], 'namespaces.html'],
            ],
            (new ClassPage())->context(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), 'App\\R'),
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
        $site = new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter());

        self::assertSame(
            '<h1><code>R</code><span class="count">5 statements</span></h1><p class="lede">5 statements in 2 methods of <code>App\\R</code>, written in <a'
                . ' href="../files/src-a-php.html">src/a.php</a>, <a href="../files/src-d-php.html">src/d.php</a>, <a href="../files/src-e-php.html">src/e.php<'
                . '/a>.</p><h2 id="tables">Tables</h2><div class="chips"><a class="chip chip-ghost" href="../tables/posts.html">posts<span class="facet-count">'
                . '4</span></a><a class="chip chip-ghost" href="../tables/users.html">users<span class="facet-count">1</span></a></div><h2 id="methods">Methods'
                . '</h2><div data-narrowable><div class="facets" role="group" aria-label="Narrow the listing"><input type="search" name="narrow" class="input f'
                . 'acet-search" placeholder="Narrow by text…" aria-label="Narrow by text" autocomplete="off" spellcheck="false"><span class="facet-group"><bu'
                . 'tton type="button" class="chip facet tone-blue" data-facet="kind" data-value="select" aria-pressed="false">SELECT<span class="facet-count">2'
                . '</span></button><button type="button" class="chip facet tone-teal" data-facet="kind" data-value="insert" aria-pressed="false">INSERT<span cl'
                . 'ass="facet-count">1</span></button><button type="button" class="chip facet tone-violet" data-facet="kind" data-value="update" aria-pressed="'
                . 'false">UPDATE<span class="facet-count">1</span></button><button type="button" class="chip facet tone-slate" data-facet="kind" data-value="sh'
                . 'ow" aria-pressed="false">SHOW<span class="facet-count">1</span></button></span><span class="facet-group"><button type="button" class="chip f'
                . 'acet tone-ok" data-facet="resolution" data-value="resolved" aria-pressed="false">resolved<span class="facet-count">4</span></button><button '
                . 'type="button" class="chip facet tone-danger" data-facet="resolution" data-value="external-input" aria-pressed="false">external-input<span cl'
                . 'ass="facet-count">1</span></button></span><span class="facet-group"><button type="button" class="chip facet tone-warn" data-facet="severity"'
                . ' data-value="medium" aria-pressed="false">medium<span class="facet-count">2</span></button><button type="button" class="chip facet tone-dang'
                . 'er" data-facet="severity" data-value="high" aria-pressed="false">high<span class="facet-count">1</span></button></span><span class="facet-sh'
                . 'own" aria-live="polite"></span><button type="button" class="btn facet-clear" hidden>Clear</button></div><section class="group" id="fn-app-r-'
                . 'find"><h3><code>R::find</code><span class="count">3</span><span class="muted">src/a.php:4</span><a class="anchor" href="#fn-app-r-find">#</a'
                . '></h3><ol class="rows"><li class="row" data-kind="select" data-resolution="external-input" data-severity="high" data-rule="external-input" d'
                . 'ata-sink="pdo.query" data-open="" data-table="posts" data-namespace="App" data-class="App\\R" data-function="App\\R::find" data-file="src/a.ph'
                . 'p"><a class="row-main" href="../statements/a1.html"><span class="chip tone-blue">SELECT</span><pre class="code"><span class="tok-kw">SELECT<'
                . '/span>
    id
<span class="tok-kw">FROM</span>
    posts
<span class="tok-kw">WHERE</span>
    slug = <span class="hole tone-danger" title="'
                . 'This is a gap: external input fills it. Written as $_GET[&quot;s&quot;].">{$}</span></pre></a><p class="row-meta"><a href="../files/src-a-ph'
                . 'p.html">src/a.php:4</a><a class="chip chip-ghost" href="../tables/posts.html">posts</a><span class="chip tone-danger" title="The values were'
                . ' followed to runtime input, so the text cannot be fixed.">external-input</span><span class="chip tone-danger" title="The most serious findin'
                . 'g on this statement">high</span></p></li><li class="row" data-kind="select" data-resolution="resolved" data-severity="medium" data-rule="dyn'
                . 'amic-sql" data-sink="pdo.query" data-open="" data-table="posts users" data-namespace="App" data-class="App\\R" data-function="App\\R::find" da'
                . 'ta-file="src/d.php"><a class="row-main" href="../statements/d1.html"><span class="chip tone-blue">SELECT</span><pre class="code"><span class'
                . '="tok-kw">SELECT</span>
    *
<span class="tok-kw">FROM</span>
    posts p
<span class="tok-kw">JOIN</span>
    users u
<span class="tok-kw"'
                . '>ON</span>
    u.id = p.author</pre></a><p class="row-meta"><a href="../files/src-d-php.html">src/d.php:1</a><a class="chip chip-ghost" href'
                . '="../tables/posts.html">posts</a><a class="chip chip-ghost" href="../tables/users.html">users</a><span class="chip tone-warn" title="The mos'
                . 't serious finding on this statement">medium</span></p></li><li class="row" data-kind="show" data-resolution="resolved" data-severity="" data'
                . '-rule="" data-sink="pdo.query" data-open="" data-table="" data-namespace="App" data-class="App\\R" data-function="App\\R::find" data-file="src'
                . '/e.php"><a class="row-main" href="../statements/e1.html"><span class="chip tone-slate">SHOW</span><pre class="code"><span class="tok-kw">SHO'
                . 'W</span> TABLES</pre></a><p class="row-meta"><a href="../files/src-e-php.html">src/e.php:1</a></p></li></ol></section><section class="group"'
                . ' id="fn-app-r-add"><h3><code>R::add</code><span class="count">2</span><span class="muted">src/a.php:9</span><a class="anchor" href="#fn-app-'
                . 'r-add">#</a></h3><ol class="rows"><li class="row" data-kind="insert" data-resolution="resolved" data-severity="" data-rule="" data-sink="pdo'
                . '.query" data-open="" data-table="posts" data-namespace="App" data-class="App\\R" data-function="App\\R::add" data-file="src/a.php"><a class="r'
                . 'ow-main" href="../statements/a2.html"><span class="chip tone-teal">INSERT</span><pre class="code"><span class="tok-kw">INSERT</span> <span c'
                . 'lass="tok-kw">INTO</span>
    posts (id)
<span class="tok-kw">VALUES</span>
    (<span class="tok-num">1</span>)</pre></a><p class="row-meta'
                . '"><a href="../files/src-a-php.html">src/a.php:9</a><a class="chip chip-ghost" href="../tables/posts.html">posts</a></p></li><li class="row" '
                . 'data-kind="update" data-resolution="resolved" data-severity="medium" data-rule="placeholder-count-mismatch" data-sink="pdo.prepare" data-ope'
                . 'n="" data-table="posts" data-namespace="App" data-class="App\\R" data-function="App\\R::add" data-file="src/a.php"><a class="row-main" href=".'
                . './statements/a3.html"><span class="chip tone-violet">UPDATE</span><pre class="code"><span class="tok-kw">UPDATE</span>
    posts
<span class'
                . '="tok-kw">SET</span>
    title = <span class="tok-var">?</span>
<span class="tok-kw">WHERE</span>
    id = <span class="tok-var">?</span></p'
                . 're></a><p class="row-meta"><a href="../files/src-a-php.html">src/a.php:14</a><a class="chip chip-ghost" href="../tables/posts.html">posts</a'
                . '><span class="chip tone-warn" title="The most serious finding on this statement">medium</span></p></li></ol></section></div>',
            (new ClassPage())->render($site, 'App\\R'),
        );
    }
}
