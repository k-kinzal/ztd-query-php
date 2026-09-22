<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\PageShell;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

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
final class PageShellTest extends TestCase
{
    public function testRenderWritesOneCompleteDocumentWithLinksRelativeToTheRoot(): void
    {
        $page = (new PageShell())->render(new ReportSite(new Catalog()), 'tables/users.html', 'users', [['SQL catalog', 'index.html'], ['users', null]], '<p>body</p>', [['Reads', 'reads']]);

        self::assertStringStartsWith('<!DOCTYPE html>' . "\n" . '<html lang="en">', $page);
        self::assertStringContainsString('<title>users — SQL catalog</title>', $page);
        self::assertStringContainsString('<link rel="stylesheet" href="../assets/report.css">', $page);
        self::assertStringContainsString('<body data-root="../">', $page);
        self::assertStringContainsString('<main class="content">' . "\n" . '<p>body</p></main>', $page);
        self::assertStringContainsString('<script src="../assets/search-index.js" defer></script>', $page);
        self::assertStringContainsString('<a href="#reads" title="Reads">Reads</a>', $page);
    }

    public function testCrumbsWriteTheTrailToThePage(): void
    {
        self::assertSame(
            '<a href="../index.html">SQL catalog</a><span class="crumb-sep">/</span><span class="crumb-current">Tables</span>',
            (new PageShell())->crumbs([['SQL catalog', 'index.html'], ['Tables', null]], '../'),
        );
    }

    public function testSidebarNamesTheReportAndWhatItHolds(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $sidebar = (new PageShell())->sidebar(new ReportSite($catalog), 'index.html', []);

        self::assertStringContainsString('<a class="sb-site" href="index.html">SQL catalog</a><span class="sb-root">1 statement</span>', $sidebar);
        self::assertStringContainsString('<p class="sb-title">Browse</p>', $sidebar);
        self::assertStringNotContainsString('On this page', $sidebar);
    }

    public function testRoutesMarkTheRouteBeingReadAndCountWhatEachHolds(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $routes = (new PageShell())->routes(new ReportSite($catalog), 'tables/users.html');

        self::assertStringContainsString('<li class="is-active"><a href="../tables.html">Tables</a><span class="sb-count">1</span></li>', $routes);
        self::assertStringContainsString('<li><a href="../index.html">Overview</a></li>', $routes);
        self::assertStringContainsString('<li><a href="../findings.html">Findings</a><span class="sb-count">0</span></li>', $routes);
    }

    public function testAnchorsListTheSectionsOfThePage(): void
    {
        self::assertSame('', (new PageShell())->anchors([]));
        self::assertSame(
            '<div class="sb-block"><p class="sb-title">On this page</p><ul class="sb-list sb-anchors"><li><a href="#a" title="A &amp; B">A &amp; B</a></li></ul></div>',
            (new PageShell())->anchors([['A & B', 'a']]),
        );
    }

    public function testBootstrapRestoresTheChosenTheme(): void
    {
        self::assertStringContainsString('localStorage.getItem("sql-catalog-theme")', (new PageShell())->bootstrap());
    }
}
