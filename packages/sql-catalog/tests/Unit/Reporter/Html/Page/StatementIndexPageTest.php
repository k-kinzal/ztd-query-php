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
use SqlCatalog\Reporter\Html\Page\StatementIndexPage;
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

#[CoversClass(StatementIndexPage::class)]
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
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(TableName::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class StatementIndexPageTest extends TestCase
{
    public function testRenderListsEveryStatementUnderTheFacetsThatNarrowIt(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);
        $page = (new StatementIndexPage())->render(new ReportSite($catalog));

        self::assertStringContainsString('<h1>Statements<span class="count">2 statements</span></h1>', $page);
        self::assertStringContainsString('<div data-narrowable><div class="facets" role="group" aria-label="Narrow the listing">', $page);
        self::assertStringContainsString('<div class="active-filters" hidden></div><ol class="rows">', $page);
        self::assertStringContainsString('href="statements/a2.html"', $page);
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
            '<h1>Statements<span class="count">18 statements</span></h1><p class="lede">Every statement the source can issue, in the order it is written. Narrow th'
                . 'e listing by what a statement does, how far the analysis got with it, or any text in it.</p><div data-narrowable><div class="facets" role="group" aria'
                . '-label="Narrow the listing"><input type="search" name="narrow" class="input facet-search" placeholder="Narrow by text…" aria-label="Narrow by text" au'
                . 'tocomplete="off" spellcheck="false"><span class="facet-group"><button type="button" class="chip facet tone-blue" data-facet="kind" data-value="select"'
                . ' aria-pressed="false">SELECT<span class="facet-count">12</span></button><button type="button" class="chip facet tone-slate" data-facet="kind" data-val'
                . 'ue="unknown" aria-pressed="false">UNKNOWN<span class="facet-count">1</span></button><button type="button" class="chip facet tone-teal" data-facet="kin'
                . 'd" data-value="insert" aria-pressed="false">INSERT<span class="facet-count">1</span></button><button type="button" class="chip facet tone-violet" data'
                . '-facet="kind" data-value="update" aria-pressed="false">UPDATE<span class="facet-count">1</span></button><button type="button" class="chip facet tone-p'
                . 'ink" data-facet="kind" data-value="delete" aria-pressed="false">DELETE<span class="facet-count">1</span></button><button type="button" class="chip fac'
                . 'et tone-indigo" data-facet="kind" data-value="alter" aria-pressed="false">ALTER<span class="facet-count">1</span></button><button type="button" class='
                . '"chip facet tone-slate" data-facet="kind" data-value="show" aria-pressed="false">SHOW<span class="facet-count">1</span></button></span><span class="fa'
                . 'cet-group"><button type="button" class="chip facet tone-ok" data-facet="resolution" data-value="resolved" aria-pressed="false">resolved<span class="fa'
                . 'cet-count">15</span></button><button type="button" class="chip facet chip-ghost" data-facet="resolution" data-value="incomplete" aria-pressed="false">'
                . 'incomplete<span class="facet-count">1</span></button><button type="button" class="chip facet tone-neutral" data-facet="resolution" data-value="not-ana'
                . 'lyzed" aria-pressed="false">not-analyzed<span class="facet-count">1</span></button><button type="button" class="chip facet tone-danger" data-facet="re'
                . 'solution" data-value="external-input" aria-pressed="false">external-input<span class="facet-count">1</span></button></span><span class="facet-group"><'
                . 'button type="button" class="chip facet tone-warn" data-facet="severity" data-value="medium" aria-pressed="false">medium<span class="facet-count">5</sp'
                . 'an></button><button type="button" class="chip facet tone-neutral" data-facet="severity" data-value="low" aria-pressed="false">low<span class="facet-co'
                . 'unt">2</span></button><button type="button" class="chip facet tone-danger" data-facet="severity" data-value="high" aria-pressed="false">high<span clas'
                . 's="facet-count">1</span></button></span><span class="facet-shown" aria-live="polite"></span><button type="button" class="btn facet-clear" hidden>Clear'
                . '</button></div><div class="active-filters" hidden></div><ol class="rows"><li class="row" data-kind="select" data-resolution="incomplete" data-severity'
                . '="low" data-rule="analysis-incomplete" data-sink="mysqli.query" data-open="open" data-table="" data-namespace="" data-class="" data-function="helper" '
                . 'data-file="lib/c.php"><a class="row-main" href="statements/c1.html"><span class="chip tone-blue">SELECT</span><span class="row-body"><span class="hole'
                . ' tone-warn" title="This is a gap: a dependency the analyzer stopped following fills it.">{$}</span></span></a><p class="row-meta"><a href="files/lib-c'
                . '-php.html">lib/c.php:2</a><a href="files/lib-c-php.html#fn-helper">helper</a><span class="chip chip-ghost" title="A cycle or an analysis budget stoppe'
                . 'd the search before it closed.">incomplete</span></p></li><li class="row" data-kind="unknown" data-resolution="not-analyzed" data-severity="low" data-'
                . 'rule="call-not-analyzed" data-sink="unmatched" data-open="open" data-table="" data-namespace="" data-class="" data-function="{main}" data-file="lib/c.'
                . 'php"><a class="row-main" href="statements/c2.html"><span class="chip tone-slate">UNKNOWN</span><span class="row-body"><span class="tok-com">no stateme'
                . 'nt was read from this call</span> $db-&gt;query($sql)</span></a><p class="row-meta"><a href="files/lib-c-php.html">lib/c.php:6</a><span class="chip to'
                . 'ne-neutral" title="The call was found but never examined, so nothing was read from it.">not-analyzed</span></p></li><li class="row" data-kind="select"'
                . ' data-resolution="external-input" data-severity="high" data-rule="external-input" data-sink="pdo.query" data-open="" data-table="posts" data-namespace'
                . '="App" data-class="App\\R" data-function="App\\R::find" data-file="src/a.php"><a class="row-main" href="statements/a1.html"><span class="chip tone-blu'
                . 'e">SELECT</span><span class="row-body"><span class="tok-kw">SELECT</span> id <span class="tok-kw">FROM</span> posts <span class="tok-kw">WHERE</span> '
                . 'slug = <span class="hole tone-danger" title="This is a gap: external input fills it. Written as $_GET[&quot;s&quot;].">{$}</span></span></a><p class="'
                . 'row-meta"><a href="files/src-a-php.html">src/a.php:4</a><a href="classes/app-r.html#fn-app-r-find">R::find</a><a class="chip chip-ghost" href="tables/'
                . 'posts.html">posts</a><span class="chip tone-danger" title="The values were followed to runtime input, so the text cannot be fixed.">external-input</sp'
                . 'an><span class="chip tone-danger" title="The most serious finding on this statement">high</span></p></li><li class="row" data-kind="insert" data-resol'
                . 'ution="resolved" data-severity="" data-rule="" data-sink="pdo.query" data-open="" data-table="posts" data-namespace="App" data-class="App\\R" data-fun'
                . 'ction="App\\R::add" data-file="src/a.php"><a class="row-main" href="statements/a2.html"><span class="chip tone-teal">INSERT</span><span class="row-bod'
                . 'y"><span class="tok-kw">INSERT</span> <span class="tok-kw">INTO</span> posts (id) <span class="tok-kw">VALUES</span> (<span class="tok-num">1</span>)<'
                . '/span></a><p class="row-meta"><a href="files/src-a-php.html">src/a.php:9</a><a href="classes/app-r.html#fn-app-r-add">R::add</a><a class="chip chip-gh'
                . 'ost" href="tables/posts.html">posts</a></p></li><li class="row" data-kind="update" data-resolution="resolved" data-severity="medium" data-rule="placeh'
                . 'older-count-mismatch" data-sink="pdo.prepare" data-open="" data-table="posts" data-namespace="App" data-class="App\\R" data-function="App\\R::add" dat'
                . 'a-file="src/a.php"><a class="row-main" href="statements/a3.html"><span class="chip tone-violet">UPDATE</span><span class="row-body"><span class="tok-k'
                . 'w">UPDATE</span> posts <span class="tok-kw">SET</span> title = <span class="tok-var">?</span> <span class="tok-kw">WHERE</span> id = <span class="tok-'
                . 'var">?</span></span></a><p class="row-meta"><a href="files/src-a-php.html">src/a.php:14</a><a href="classes/app-r.html#fn-app-r-add">R::add</a><a clas'
                . 's="chip chip-ghost" href="tables/posts.html">posts</a><span class="chip tone-warn" title="The most serious finding on this statement">medium</span></p'
                . '></li><li class="row" data-kind="delete" data-resolution="resolved" data-severity="" data-rule="" data-sink="pdo.query" data-open="" data-table="users'
                . '" data-namespace="App\\Admin" data-class="App\\Admin\\U" data-function="App\\Admin\\U::drop" data-file="src/b.php"><a class="row-main" href="statement'
                . 's/b1.html"><span class="chip tone-pink">DELETE</span><span class="row-body"><span class="tok-kw">DELETE</span> <span class="tok-kw">FROM</span> users '
                . '<span class="tok-kw">WHERE</span> id = <span class="tok-num">1</span></span></a><p class="row-meta"><a href="files/src-b-php.html">src/b.php:3</a><a h'
                . 'ref="classes/app-admin-u.html#fn-app-admin-u-drop">U::drop</a><a class="chip chip-ghost" href="tables/users.html">users</a></p></li><li class="row" da'
                . 'ta-kind="alter" data-resolution="resolved" data-severity="" data-rule="" data-sink="pdo.exec" data-open="" data-table="users" data-namespace="App\\Adm'
                . 'in" data-class="App\\Admin\\U" data-function="App\\Admin\\U::migrate" data-file="src/b.php"><a class="row-main" href="statements/b2.html"><span class='
                . '"chip tone-indigo">ALTER</span><span class="row-body"><span class="tok-kw">ALTER</span> <span class="tok-kw">TABLE</span> users <span class="tok-kw">A'
                . 'DD</span> x INT</span></a><p class="row-meta"><a href="files/src-b-php.html">src/b.php:8</a><a href="classes/app-admin-u.html#fn-app-admin-u-migrate">'
                . 'U::migrate</a><a class="chip chip-ghost" href="tables/users.html">users</a></p></li><li class="row" data-kind="select" data-resolution="resolved" data'
                . '-severity="medium" data-rule="dynamic-sql" data-sink="pdo.query" data-open="" data-table="posts users" data-namespace="App" data-class="App\\R" data-f'
                . 'unction="App\\R::find" data-file="src/d.php"><a class="row-main" href="statements/d1.html"><span class="chip tone-blue">SELECT</span><span class="row-'
                . 'body"><span class="tok-kw">SELECT</span> * <span class="tok-kw">FROM</span> posts p <span class="tok-kw">JOIN</span> users u <span class="tok-kw">ON</'
                . 'span> u.id = p.author</span></a><p class="row-meta"><a href="files/src-d-php.html">src/d.php:1</a><a href="classes/app-r.html#fn-app-r-find">R::find</'
                . 'a><a class="chip chip-ghost" href="tables/posts.html">posts</a><a class="chip chip-ghost" href="tables/users.html">users</a><span class="chip tone-war'
                . 'n" title="The most serious finding on this statement">medium</span></p></li><li class="row" data-kind="show" data-resolution="resolved" data-severity='
                . '"" data-rule="" data-sink="pdo.query" data-open="" data-table="" data-namespace="App" data-class="App\\R" data-function="App\\R::find" data-file="src/'
                . 'e.php"><a class="row-main" href="statements/e1.html"><span class="chip tone-slate">SHOW</span><span class="row-body"><span class="tok-kw">SHOW</span> '
                . 'TABLES</span></a><p class="row-meta"><a href="files/src-e-php.html">src/e.php:1</a><a href="classes/app-r.html#fn-app-r-find">R::find</a></p></li><li '
                . 'class="row" data-kind="select" data-resolution="resolved" data-severity="" data-rule="" data-sink="pdo.query" data-open="" data-table="t1" data-namesp'
                . 'ace="App" data-class="App\\F" data-function="App\\F::a" data-file="src/f.php"><a class="row-main" href="statements/f1.html"><span class="chip tone-blu'
                . 'e">SELECT</span><span class="row-body"><span class="tok-kw">SELECT</span> <span class="tok-num">1</span> <span class="tok-kw">FROM</span> t1</span></a'
                . '><p class="row-meta"><a href="files/src-f-php.html">src/f.php:1</a><a href="classes/app-f.html#fn-app-f-a">F::a</a><a class="chip chip-ghost" href="ta'
                . 'bles/t1.html">t1</a></p></li><li class="row" data-kind="select" data-resolution="resolved" data-severity="" data-rule="" data-sink="pdo.query" data-op'
                . 'en="" data-table="t2" data-namespace="App" data-class="App\\F" data-function="App\\F::b" data-file="src/f.php"><a class="row-main" href="statements/f2'
                . '.html"><span class="chip tone-blue">SELECT</span><span class="row-body"><span class="tok-kw">SELECT</span> <span class="tok-num">2</span> <span class='
                . '"tok-kw">FROM</span> t2</span></a><p class="row-meta"><a href="files/src-f-php.html">src/f.php:2</a><a href="classes/app-f.html#fn-app-f-b">F::b</a><a'
                . ' class="chip chip-ghost" href="tables/t2.html">t2</a></p></li><li class="row" data-kind="select" data-resolution="resolved" data-severity="" data-rule'
                . '="" data-sink="pdo.query" data-open="" data-table="t3" data-namespace="App" data-class="App\\G" data-function="App\\G::a" data-file="src/f.php"><a cla'
                . 'ss="row-main" href="statements/f3.html"><span class="chip tone-blue">SELECT</span><span class="row-body"><span class="tok-kw">SELECT</span> <span clas'
                . 's="tok-num">3</span> <span class="tok-kw">FROM</span> t3</span></a><p class="row-meta"><a href="files/src-f-php.html">src/f.php:3</a><a href="classes/'
                . 'app-g.html#fn-app-g-a">G::a</a><a class="chip chip-ghost" href="tables/t3.html">t3</a></p></li><li class="row" data-kind="select" data-resolution="res'
                . 'olved" data-severity="" data-rule="" data-sink="pdo.query" data-open="" data-table="t4" data-namespace="App" data-class="App\\H" data-function="App\\H'
                . '::a" data-file="src/f.php"><a class="row-main" href="statements/f4.html"><span class="chip tone-blue">SELECT</span><span class="row-body"><span class='
                . '"tok-kw">SELECT</span> <span class="tok-num">4</span> <span class="tok-kw">FROM</span> t4</span></a><p class="row-meta"><a href="files/src-f-php.html"'
                . '>src/f.php:4</a><a href="classes/app-h.html#fn-app-h-a">H::a</a><a class="chip chip-ghost" href="tables/t4.html">t4</a></p></li><li class="row" data-k'
                . 'ind="select" data-resolution="resolved" data-severity="" data-rule="" data-sink="pdo.query" data-open="" data-table="t5" data-namespace="App" data-cla'
                . 'ss="App\\I" data-function="App\\I::a" data-file="src/g.php"><a class="row-main" href="statements/f5.html"><span class="chip tone-blue">SELECT</span><s'
                . 'pan class="row-body"><span class="tok-kw">SELECT</span> <span class="tok-num">5</span> <span class="tok-kw">FROM</span> t5</span></a><p class="row-met'
                . 'a"><a href="files/src-g-php.html">src/g.php:1</a><a href="classes/app-i.html#fn-app-i-a">I::a</a><a class="chip chip-ghost" href="tables/t5.html">t5</'
                . 'a></p></li><li class="row" data-kind="select" data-resolution="resolved" data-severity="" data-rule="" data-sink="pdo.query" data-open="" data-table="'
                . 't6" data-namespace="App" data-class="App\\J" data-function="App\\J::a" data-file="src/h.php"><a class="row-main" href="statements/f6.html"><span class'
                . '="chip tone-blue">SELECT</span><span class="row-body"><span class="tok-kw">SELECT</span> <span class="tok-num">6</span> <span class="tok-kw">FROM</spa'
                . 'n> t6</span></a><p class="row-meta"><a href="files/src-h-php.html">src/h.php:1</a><a href="classes/app-j.html#fn-app-j-a">J::a</a><a class="chip chip-'
                . 'ghost" href="tables/t6.html">t6</a></p></li><li class="row" data-kind="select" data-resolution="resolved" data-severity="medium" data-rule="dynamic-sq'
                . 'l" data-sink="pdo.query" data-open="" data-table="t7" data-namespace="App" data-class="App\\K" data-function="App\\K::a" data-file="src/i.php"><a clas'
                . 's="row-main" href="statements/f7.html"><span class="chip tone-blue">SELECT</span><span class="row-body"><span class="tok-kw">SELECT</span> <span class'
                . '="tok-num">7</span> <span class="tok-kw">FROM</span> t7</span></a><p class="row-meta"><a href="files/src-i-php.html">src/i.php:1</a><a href="classes/a'
                . 'pp-k.html#fn-app-k-a">K::a</a><a class="chip chip-ghost" href="tables/t7.html">t7</a><span class="chip tone-warn" title="The most serious finding on t'
                . 'his statement">medium</span></p></li><li class="row" data-kind="select" data-resolution="resolved" data-severity="medium" data-rule="dynamic-sql" data'
                . '-sink="pdo.query" data-open="" data-table="t8" data-namespace="App" data-class="App\\L" data-function="App\\L::a" data-file="src/j.php"><a class="row-'
                . 'main" href="statements/f8.html"><span class="chip tone-blue">SELECT</span><span class="row-body"><span class="tok-kw">SELECT</span> <span class="tok-n'
                . 'um">8</span> <span class="tok-kw">FROM</span> t8</span></a><p class="row-meta"><a href="files/src-j-php.html">src/j.php:1</a><a href="classes/app-l.ht'
                . 'ml#fn-app-l-a">L::a</a><a class="chip chip-ghost" href="tables/t8.html">t8</a><span class="chip tone-warn" title="The most serious finding on this sta'
                . 'tement">medium</span></p></li><li class="row" data-kind="select" data-resolution="resolved" data-severity="medium" data-rule="dynamic-sql" data-sink="'
                . 'pdo.query" data-open="" data-table="t9" data-namespace="App" data-class="App\\M" data-function="App\\M::a" data-file="src/k.php"><a class="row-main" h'
                . 'ref="statements/f9.html"><span class="chip tone-blue">SELECT</span><span class="row-body"><span class="tok-kw">SELECT</span> <span class="tok-num">9</'
                . 'span> <span class="tok-kw">FROM</span> t9</span></a><p class="row-meta"><a href="files/src-k-php.html">src/k.php:1</a><a href="classes/app-m.html#fn-a'
                . 'pp-m-a">M::a</a><a class="chip chip-ghost" href="tables/t9.html">t9</a><span class="chip tone-warn" title="The most serious finding on this statement"'
                . '>medium</span></p></li></ol></div>',
            (new StatementIndexPage())->render($site),
        );
    }
}
