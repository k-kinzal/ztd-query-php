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
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\PageShell;
use SqlCatalog\Reporter\Html\ReportSite;
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
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class PageShellTest extends TestCase
{
    public function testRenderWritesOneCompleteDocument(): void
    {
        $page = (new PageShell())->render(
            new ReportSite(new Catalog()),
            ReportSite::INDEX,
            'Overview',
            [['SQL catalog', null]],
            '<p>body</p>',
        );

        self::assertStringStartsWith('<!DOCTYPE html>', $page);
        self::assertStringContainsString('<title>Overview — SQL catalog</title>', $page);
        self::assertStringContainsString('<link rel="stylesheet" href="assets/report.css">', $page);
        self::assertStringContainsString('<main class="content">' . "\n" . '<p>body</p></main>', $page);
        self::assertStringEndsWith('</html>' . "\n", $page);
    }

    public function testRenderWritesTheLinksOfANestedPageRelativeToTheRoot(): void
    {
        $page = (new PageShell())->render(
            new ReportSite(new Catalog()),
            'statements/page-1.html',
            'Statements',
            [],
            '',
        );

        self::assertStringContainsString('<body data-root="../">', $page);
        self::assertStringContainsString('href="../assets/report.css"', $page);
        self::assertStringContainsString('src="../assets/report.js"', $page);
    }

    public function testCrumbsWriteTheTrailToThePage(): void
    {
        self::assertSame(
            '<a href="../index.html">SQL catalog</a><span class="crumb-sep">/</span>'
            . '<span class="crumb-current">Page 1</span>',
            (new PageShell())->crumbs([['SQL catalog', ReportSite::INDEX], ['Page 1', null]], '../'),
        );
    }

    public function testSidebarNamesTheReportAndWhatItHolds(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $sidebar = (new PageShell())->sidebar(new ReportSite($catalog), ReportSite::INDEX);

        self::assertStringContainsString('<a class="sb-site" href="index.html">SQL catalog</a>', $sidebar);
        self::assertStringContainsString('<span class="sb-root">1 file</span>', $sidebar);
    }

    public function testReportBlockMarksThePageBeingRead(): void
    {
        self::assertSame(
            '<div class="sb-block"><p class="sb-title">Report</p><ul class="sb-list">'
            . '<li><a href="index.html">Overview</a></li>'
            . '<li class="is-active"><a href="tables.html">Tables</a></li>'
            . '<li><a href="findings.html">Findings</a></li></ul></div>',
            (new PageShell())->reportBlock(new ReportSite(new Catalog()), ReportSite::TABLES),
        );
    }

    public function testFilesBlockIsSilentOnAPageThatListsNoStatements(): void
    {
        self::assertSame('', (new PageShell())->filesBlock(new ReportSite(new Catalog()), ReportSite::INDEX));
    }

    public function testFilesBlockLinksToTheFilesOfThePageBeingRead(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertStringContainsString(
            '<li><a href="#file-src-a-php" title="src/a.php">src/a.php</a><span class="sb-count">1</span></li>',
            (new PageShell())->filesBlock(new ReportSite($catalog), 'statements/page-1.html'),
        );
    }

    public function testPagesBlockIsSilentWhenThereIsOnlyOnePage(): void
    {
        self::assertSame('', (new PageShell())->pagesBlock(new ReportSite(new Catalog()), ReportSite::INDEX));
    }

    public function testPagesBlockLinksToEveryPageOfStatements(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            '<div class="sb-block"><p class="sb-title">Statements</p><ul class="sb-list">'
            . '<li class="is-active"><a href="../statements/page-1.html">Page 1</a><span class="sb-count">1</span></li>'
            . '<li><a href="../statements/page-2.html">Page 2</a><span class="sb-count">1</span></li></ul></div>',
            (new PageShell())->pagesBlock(new ReportSite($catalog, 1), 'statements/page-1.html'),
        );
    }

    public function testNumberOfSaysWhichPageOfStatementsIsBeingRead(): void
    {
        $shell = new PageShell();
        $site = new ReportSite(new Catalog());

        self::assertSame(1, $shell->numberOf($site, 'statements/page-1.html'));
        self::assertNull($shell->numberOf($site, ReportSite::INDEX));
    }

    public function testBootstrapRestoresTheChosenTheme(): void
    {
        self::assertStringContainsString('sql-catalog-theme', (new PageShell())->bootstrap());
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
            '<!DOCTYPE html>'
                . "\n"
                . '<html lang="en">'
                . "\n"
                . '<head>'
                . "\n"
                . '<meta charset="utf-8">'
                . "\n"
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . "\n"
                . '<title>Overview — SQL catalog</title>'
                . "\n"
                . '<link rel="stylesheet" href="assets/report.css">'
                . "\n"
                . '<script>try{var t=localStorage.getItem("sql-catalog-theme");if(t){document.documentElement.dataset.theme=t}}catch(e){}</script>'
                . "\n"
                . '</head>'
                . "\n"
                . '<body data-root="">'
                . "\n"
                . '<nav class="sidebar" id="sidebar"><div class="sb-head"><a class="sb-site" href="index.html">SQL catalog</a><span class="sb-root">2 files</sp'
                . 'an></div><div class="sb-block"><p class="sb-title">Report</p><ul class="sb-list"><li class="is-active"><a href="index.html">Overview</a></li'
                . '><li><a href="tables.html">Tables</a></li><li><a href="findings.html">Findings</a></li></ul></div></nav>'
                . "\n"
                . '<div class="page">'
                . "\n"
                . '<header class="topbar">'
                . "\n"
                . '<button class="nav-toggle" id="nav-toggle" title="Toggle navigation">☰</button>'
                . "\n"
                . '<nav class="crumbs"><span class="crumb-current">SQL catalog</span></nav>'
                . "\n"
                . '<div class="topbar-tools">'
                . "\n"
                . '<input type="search" id="search" placeholder="Search statements… ( / )" autocomplete="off" spellcheck="false">'
                . "\n"
                . '<button id="theme-toggle" title="Toggle theme">◐</button>'
                . "\n"
                . '</div>'
                . "\n"
                . '</header>'
                . "\n"
                . '<div class="search-results" id="search-results" hidden></div>'
                . "\n"
                . '<main class="content">'
                . "\n"
                . '<p>body</p></main>'
                . "\n"
                . '<footer class="site-footer">Every statement here was read back from the call that receives it. A gap marked <span class="hole">{$}</span> is'
                . ' a value the analysis could not pin down, not a value the program leaves empty.</footer>'
                . "\n"
                . '</div>'
                . "\n"
                . '<script src="assets/search-index.js" defer></script>'
                . "\n"
                . '<script src="assets/report.js" defer></script>'
                . "\n"
                . '</body>'
                . "\n"
                . '</html>'
                . "\n",
            (new PageShell())->render($site, ReportSite::INDEX, 'Overview', [['SQL catalog', null]], '<p>body</p>'),
        );
    }

    public function testRenderIsWrittenExactlyForAPageOfStatements(): void
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

        $site = new ReportSite($catalog, 1);

        self::assertSame(
            '<!DOCTYPE html>'
                . "\n"
                . '<html lang="en">'
                . "\n"
                . '<head>'
                . "\n"
                . '<meta charset="utf-8">'
                . "\n"
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . "\n"
                . '<title>Statements, page 1 — SQL catalog</title>'
                . "\n"
                . '<link rel="stylesheet" href="../assets/report.css">'
                . "\n"
                . '<script>try{var t=localStorage.getItem("sql-catalog-theme");if(t){document.documentElement.dataset.theme=t}}catch(e){}</script>'
                . "\n"
                . '</head>'
                . "\n"
                . '<body data-root="../">'
                . "\n"
                . '<nav class="sidebar" id="sidebar"><div class="sb-head"><a class="sb-site" href="../index.html">SQL catalog</a><span class="sb-root">2 files<'
                . '/span></div><div class="sb-block"><p class="sb-title">Report</p><ul class="sb-list"><li><a href="../index.html">Overview</a></li><li><a href'
                . '="../tables.html">Tables</a></li><li><a href="../findings.html">Findings</a></li></ul></div><div class="sb-block"><p class="sb-title">On thi'
                . 's page</p><ul class="sb-list"><li><a href="#file-src-a-php" title="src/a.php">src/a.php</a><span class="sb-count">1</span></li></ul></div><d'
                . 'iv class="sb-block"><p class="sb-title">Statements</p><ul class="sb-list"><li class="is-active"><a href="../statements/page-1.html">Page 1</'
                . 'a><span class="sb-count">1</span></li><li><a href="../statements/page-2.html">Page 2</a><span class="sb-count">1</span></li></ul></div></nav'
                . '>'
                . "\n"
                . '<div class="page">'
                . "\n"
                . '<header class="topbar">'
                . "\n"
                . '<button class="nav-toggle" id="nav-toggle" title="Toggle navigation">☰</button>'
                . "\n"
                . '<nav class="crumbs"><a href="../index.html">SQL catalog</a><span class="crumb-sep">/</span><span class="crumb-current">Statements</span><spa'
                . 'n class="crumb-sep">/</span><span class="crumb-current">Page 1</span></nav>'
                . "\n"
                . '<div class="topbar-tools">'
                . "\n"
                . '<input type="search" id="search" placeholder="Search statements… ( / )" autocomplete="off" spellcheck="false">'
                . "\n"
                . '<button id="theme-toggle" title="Toggle theme">◐</button>'
                . "\n"
                . '</div>'
                . "\n"
                . '</header>'
                . "\n"
                . '<div class="search-results" id="search-results" hidden></div>'
                . "\n"
                . '<main class="content">'
                . "\n"
                . '<p>body</p></main>'
                . "\n"
                . '<footer class="site-footer">Every statement here was read back from the call that receives it. A gap marked <span class="hole">{$}</span> is'
                . ' a value the analysis could not pin down, not a value the program leaves empty.</footer>'
                . "\n"
                . '</div>'
                . "\n"
                . '<script src="../assets/search-index.js" defer></script>'
                . "\n"
                . '<script src="../assets/report.js" defer></script>'
                . "\n"
                . '</body>'
                . "\n"
                . '</html>'
                . "\n",
            (new PageShell())->render(
                $site,
                $site->pageName(1),
                'Statements, page 1',
                [['SQL catalog', ReportSite::INDEX], ['Statements', null], ['Page 1', null]],
                '<p>body</p>',
            ),
        );
    }
}
