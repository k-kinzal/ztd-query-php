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
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\TablePage;
use SqlCatalog\Reporter\Html\PageShell;
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

#[CoversClass(PageShell::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Scope::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Severity::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(Palette::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(TableName::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(TablePage::class)]
final class PageShellTest extends TestCase
{
    public function testRenderWritesOneCompleteDocumentWithLinksRelativeToTheRoot(): void
    {
        $page = (new PageShell())->render(new ReportSite(new Catalog()), 'tables/users.html', 'users', [['Overview', 'index.html'], ['users', null]], '<p>body</p>', [['On this page', [['Reads', '#reads', null, false]], null]]);

        self::assertStringStartsWith('<!DOCTYPE html>' . "\n" . '<html lang="en" data-dd-theme-key="sql-catalog-theme">', $page);
        self::assertStringContainsString('<title>users</title>', $page);
        self::assertStringContainsString('<link rel="stylesheet" href="../assets/document-design-v1.0.0.css">' . "\n" . '<link rel="stylesheet" href="../assets/report.css">', $page);
        self::assertStringContainsString('<body data-root="../">', $page);
        self::assertStringContainsString('<div class="doc">' . "\n" . '<nav class="sidebar" id="navigation" aria-label="Report navigation">', $page);
        self::assertStringContainsString('<main class="content" id="content">' . "\n" . '<p>body</p></main>', $page);
        self::assertStringContainsString('<script src="../assets/search-index.js" defer></script>' . "\n" . '<script src="../assets/report.js" defer></script>' . "\n" . '<script src="../assets/document-design-v1.0.0.js" defer></script>', $page);
        self::assertStringContainsString('<ul class="sidebar-list sidebar-context" data-dd-toc><li><a href="#reads" title="Reads">Reads</a></li></ul>', $page);
    }

    public function testCrumbsWriteTheTrailToThePage(): void
    {
        self::assertSame(
            '<a href="../index.html">Overview</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Tables</span>',
            (new PageShell())->crumbs([['Overview', 'index.html'], ['Tables', null]], '../'),
        );
    }

    public function testSidebarWritesTheRoutesAndThenWhatThePageIsLeftFrom(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $sidebar = (new PageShell())->sidebar(new ReportSite($catalog), 'tables/t.html', [['Tables', [['t', 'tables/t.html', 1, true]], 'tables.html']]);

        self::assertStringStartsWith('<div class="sidebar-section"><p class="sidebar-title">Browse</p>', $sidebar);
        self::assertStringEndsWith('<div class="sidebar-section"><p class="sidebar-title">Tables</p><ul class="sidebar-list sidebar-context"><li class="is-active"><a href="../tables/t.ht'
            . 'ml" title="t" aria-current="page">t</a><span class="sidebar-count">1</span></li></ul></div>', $sidebar);
        self::assertStringNotContainsString('SQL catalog', $sidebar);
    }

    public function testRoutesMarkTheRouteBeingReadAndCountWhatEachHolds(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $routes = (new PageShell())->routes(new ReportSite($catalog), 'tables/users.html');

        self::assertStringContainsString('<li class="is-active"><a href="../tables.html" aria-current="page">Tables</a><span class="sidebar-count">1</span></li>', $routes);
        self::assertStringContainsString('<li><a href="../index.html">Overview</a></li>', $routes);
        self::assertStringContainsString('<li><a href="../findings.html">Findings</a><span class="sidebar-count">0</span></li>', $routes);
    }

    public function testBlockWritesSectionsAsTheyAreAndPagesRelativeToTheRoot(): void
    {
        $shell = new PageShell();

        self::assertSame('', $shell->block('Empty', [], null, '../'));
        self::assertSame(
            '<div class="sidebar-section"><p class="sidebar-title">On this page</p><ul class="sidebar-list sidebar-context"><li><a href="#a" title="A &amp; B">A &a'
                . 'mp; B</a></li><li class="is-active"><a href="../tables/t.html" title="t" aria-current="page">t</a><span class="sidebar-count">3</span></li></ul></div>',
            $shell->block('On this page', [['A & B', '#a', null, false], ['t', 'tables/t.html', 3, true]], null, '../'),
        );
        self::assertSame(
            '<div class="sidebar-section"><p class="sidebar-title">On this page</p><ul class="sidebar-list sidebar-context" data-dd-toc><li><a href="#a" title="A">A</a></li></ul></div>',
            $shell->block('On this page', [['A', '#a', null, false]], null, '../'),
        );
    }

    public function testBlockCutsALongListAndSaysWhereTheRestAre(): void
    {
        $items = array_map(static fn (int $n): array => ['t' . $n, 'tables/t' . $n . '.html', $n, false], range(1, 41));
        $block = (new PageShell())->block('Tables', $items, 'tables.html', '');

        self::assertStringContainsString('title="t40"', $block);
        self::assertStringNotContainsString('title="t41"', $block);
        self::assertStringEndsWith('<li class="sidebar-more"><a href="tables.html">All 41…</a></li></ul></div>', $block);
        self::assertStringNotContainsString('sidebar-more', (new PageShell())->block('Tables', array_slice($items, 0, 40), 'tables.html', ''));
    }

    public function testOnThisPageTurnsAnchorsIntoABlock(): void
    {
        self::assertSame([], (new PageShell())->onThisPage([]));
        self::assertSame([['On this page', [['Reads', '#reads', null, false]], null]], (new PageShell())->onThisPage([['Reads', 'reads']]));
    }

    public function testHeadLoadsTheDesignBeforeTheReportsOwnStylesheet(): void
    {
        $head = (new PageShell())->head('../', 'A & B');

        self::assertStringStartsWith('<!DOCTYPE html>' . "\n" . '<html lang="en" data-dd-theme-key="sql-catalog-theme">' . "\n" . '<head>', $head);
        self::assertStringContainsString('<meta name="color-scheme" content="light dark">', $head);
        self::assertStringContainsString('<title>A &amp; B</title>', $head);
        self::assertStringContainsString('<link rel="stylesheet" href="../assets/document-design-v1.0.0.css">' . "\n" . '<link rel="stylesheet" href="../assets/report.css">', $head);
        self::assertStringEndsWith((new PageShell())->bootstrap() . "\n" . '</head>' . "\n", $head);
    }

    public function testTopbarCarriesTheTrailAndTheControlsTheScriptReveals(): void
    {
        $topbar = (new PageShell())->topbar([['Overview', 'index.html'], ['Tables', null]], '../');

        self::assertStringStartsWith('<header class="topbar">' . "\n" . '<button class="btn nav-toggle" type="button" data-dd-nav-toggle aria-controls="navigation" aria-expanded="false"', $topbar);
        self::assertStringContainsString('<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="../index.html">Overview</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Tables</span></nav>', $topbar);
        self::assertStringContainsString('<input type="search" id="search" class="input input-search" data-dd-search data-dd-enhance hidden', $topbar);
        self::assertStringContainsString('<button class="btn" type="button" data-dd-theme-toggle data-dd-enhance hidden aria-label="Switch theme">◐</button>', $topbar);
        self::assertStringEndsWith('</header>' . "\n", $topbar);
    }

    public function testBootstrapRestoresTheChosenTheme(): void
    {
        self::assertSame('<script>try{var t=localStorage.getItem("sql-catalog-theme");if(t){document.documentElement.dataset.ddTheme=t}}catch(e){}</script>', (new PageShell())->bootstrap());
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
            '<!DOCTYPE html>
<html lang="en" data-dd-theme-key="sql-catalog-theme">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width'
                . ', initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>posts</title>
<link rel="stylesheet" href="../assets/document-design-v1.0.0'
                . '.css">
<link rel="stylesheet" href="../assets/report.css">
<script>try{var t=localStorage.getItem("sql-catalog-theme");if(t){document.documentElement.'
                . 'dataset.ddTheme=t}}catch(e){}</script>
</head>
<body data-root="../">
<a class="skip" href="#content">Skip to content</a>
<div class="doc">
<nav class'
                . '="sidebar" id="navigation" aria-label="Report navigation"><div class="sidebar-section"><p class="sidebar-title">Browse</p><ul class="sidebar-list"><li'
                . '><a href="../index.html">Overview</a></li><li><a href="../statements.html">Statements</a><span class="sidebar-count">18</span></li><li class="is-activ'
                . 'e"><a href="../tables.html" aria-current="page">Tables</a><span class="sidebar-count">11</span></li><li><a href="../namespaces.html">Namespaces</a><sp'
                . 'an class="sidebar-count">3</span></li><li><a href="../files.html">Files</a><span class="sidebar-count">11</span></li><li><a href="../findings.html">Fi'
                . 'ndings</a><span class="sidebar-count">8</span></li></ul></div><div class="sidebar-section"><p class="sidebar-title">On this page</p><ul class="sidebar'
                . '-list sidebar-context" data-dd-toc><li><a href="#used-from" title="Used from">Used from</a></li><li><a href="#alongside" title="Named alongside">Named'
                . ' alongside</a></li><li><a href="#writes" title="Writes">Writes</a></li><li><a href="#reads" title="Reads">Reads</a></li></ul></div><div class="sidebar'
                . '-section"><p class="sidebar-title">Tables</p><ul class="sidebar-list sidebar-context"><li class="is-active"><a href="../tables/posts.html" title="post'
                . 's" aria-current="page">posts</a><span class="sidebar-count">4</span></li><li><a href="../tables/users.html" title="users">users</a><span class="sideba'
                . 'r-count">3</span></li><li><a href="../tables/t1.html" title="t1">t1</a><span class="sidebar-count">1</span></li><li><a href="../tables/t2.html" title='
                . '"t2">t2</a><span class="sidebar-count">1</span></li><li><a href="../tables/t3.html" title="t3">t3</a><span class="sidebar-count">1</span></li><li><a h'
                . 'ref="../tables/t4.html" title="t4">t4</a><span class="sidebar-count">1</span></li><li><a href="../tables/t5.html" title="t5">t5</a><span class="sideba'
                . 'r-count">1</span></li><li><a href="../tables/t6.html" title="t6">t6</a><span class="sidebar-count">1</span></li><li><a href="../tables/t7.html" title='
                . '"t7">t7</a><span class="sidebar-count">1</span></li><li><a href="../tables/t8.html" title="t8">t8</a><span class="sidebar-count">1</span></li><li><a h'
                . 'ref="../tables/t9.html" title="t9">t9</a><span class="sidebar-count">1</span></li></ul></div></nav>
<div class="main">
<header class="topbar">
<button'
                . ' class="btn nav-toggle" type="button" data-dd-nav-toggle aria-controls="navigation" aria-expanded="false" aria-label="Open navigation">☰</button>
<nav'
                . ' class="breadcrumbs" aria-label="Breadcrumb"><a href="../index.html">Overview</a><span class="breadcrumb-sep">/</span><a href="../tables.html">Tables<'
                . '/a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">posts</span></nav>
<div class="topbar-tools">
<input type="search" id="search'
                . '" class="input input-search" data-dd-search data-dd-enhance hidden placeholder="Find a statement… ( / )" aria-label="Search by SQL text, table, functi'
                . 'on or file" autocomplete="off" spellcheck="false">
<button class="btn" type="button" data-dd-theme-toggle data-dd-enhance hidden aria-label="Switch th'
                . 'eme">◐</button>
</div>
</header>
<div class="search-results" data-dd-search-results hidden></div>
<main class="content" id="content">
<p>body</p></mai'
                . 'n>
<footer class="doc-footer">Written by <a href="https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-catalog">sql-catalog</a>.</footer>
'
                . '</div>
</div>
<script src="../assets/search-index.js" defer></script>
<script src="../assets/report.js" defer></script>
<script src="../assets/documen'
                . 't-design-v1.0.0.js" defer></script>
</body>
</html>
',
            (new PageShell())->render($site, 'tables/posts.html', 'posts', [['Overview', 'index.html'], ['Tables', 'tables.html'], ['posts', null]], '<p>body</p>', (new TablePage())->context($site, 'posts')),
        );
    }
}
