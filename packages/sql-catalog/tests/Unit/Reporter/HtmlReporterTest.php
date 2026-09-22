<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Reporter\CatalogArtifacts;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\ClassPage;
use SqlCatalog\Reporter\Html\Page\FileIndexPage;
use SqlCatalog\Reporter\Html\Page\FilePage;
use SqlCatalog\Reporter\Html\Page\FindingPage;
use SqlCatalog\Reporter\Html\Page\NamespacePage;
use SqlCatalog\Reporter\Html\Page\OverviewPage;
use SqlCatalog\Reporter\Html\Page\StatementIndexPage;
use SqlCatalog\Reporter\Html\Page\StatementPage;
use SqlCatalog\Reporter\Html\Page\TableIndexPage;
use SqlCatalog\Reporter\Html\Page\TablePage;
use SqlCatalog\Reporter\Html\PageShell;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportAssets;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SearchIndex;
use SqlCatalog\Reporter\Html\SqlFormatter;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\StatementRow;
use SqlCatalog\Reporter\Html\TableName;
use SqlCatalog\Reporter\HtmlReporter;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(HtmlReporter::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogArtifacts::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(ClassPage::class)]
#[UsesClass(FileIndexPage::class)]
#[UsesClass(FilePage::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(FindingPage::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(NamespacePage::class)]
#[UsesClass(Origin::class)]
#[UsesClass(OverviewPage::class)]
#[UsesClass(PageShell::class)]
#[UsesClass(Palette::class)]
#[UsesClass(ReportAssets::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Scope::class)]
#[UsesClass(SearchIndex::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementIndexPage::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementPage::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(TableIndexPage::class)]
#[UsesClass(TableName::class)]
#[UsesClass(TablePage::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(StatementKind::class)]
#[Medium]
final class HtmlReporterTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('html', (new HtmlReporter())->name());
    }

    public function testDescriptionMentionsWhatItProduces(): void
    {
        self::assertStringContainsString('HTML', (new HtmlReporter())->description());
    }

    public function testRenderWritesTheWholeSiteOfPages(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT * FROM posts'), ['posts'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ]),
        ]);
        $artifacts = (new HtmlReporter())->render($catalog);

        self::assertSame(
            [
                'assets/report.css',
                'assets/report.js',
                'assets/search-index.js',
                'classes/app-r.html',
                'files.html',
                'files/src-a-php.html',
                'findings.html',
                'index.html',
                'namespaces.html',
                'statements.html',
                'statements/a1.html',
                'tables.html',
                'tables/posts.html',
            ],
            $artifacts->names(),
        );
    }

    public function testRenderNamesThePageAReaderOpensFirst(): void
    {
        $artifacts = (new HtmlReporter())->render(new Catalog());

        self::assertSame($artifacts->get(HtmlReporter::FILE), $artifacts->primary());
        self::assertStringContainsString('<title>Overview — SQL catalog</title>', (string) $artifacts->primary());
    }

    public function testRenderLinksEveryRouteToTheStatementsPage(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT * FROM posts'), ['posts'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ]),
        ]);
        $artifacts = (new HtmlReporter())->render($catalog);

        self::assertStringContainsString('href="../statements/a1.html"', (string) $artifacts->get('tables/posts.html'));
        self::assertStringContainsString('href="../statements/a1.html"', (string) $artifacts->get('classes/app-r.html'));
        self::assertStringContainsString('href="../statements/a1.html"', (string) $artifacts->get('files/src-a-php.html'));
        self::assertStringContainsString('href="statements/a1.html"', (string) $artifacts->get('findings.html'));
        self::assertStringContainsString('"u":"statements/a1.html"', (string) $artifacts->get('assets/search-index.js'));
    }

    public function testRenderKeepsACallNoStatementWasReadFromApartFromAStatement(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $page = (string) (new HtmlReporter())->render($catalog)->get('statements/a1.html');

        self::assertStringContainsString('no statement was read from this call', $page);
        self::assertStringContainsString('<span class="chip s-neutral">not-analyzed</span>', $page);
    }

    public function testRenderCarriesTheFilesThatCouldNotBeRead(): void
    {
        $overview = (string) (new HtmlReporter())->render(new Catalog([], [new AnalysisProblem('b.php', 'broken')]))->get('index.html');

        self::assertStringContainsString('<tr><td><code>b.php</code></td><td>broken</td></tr>', $overview);
    }

    public function testTablePagesWriteOneDocumentPerTable(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $pages = (new HtmlReporter())->tablePages(new ReportSite($catalog), new PageShell());

        self::assertSame(['tables/posts.html', 'tables/users.html'], array_keys($pages));
        self::assertStringContainsString('<title>posts — SQL catalog</title>', $pages['tables/posts.html']);
    }

    public function testClassPagesWriteOneDocumentPerClass(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'helper', 'pdo.query'), []),
        ]);
        $pages = (new HtmlReporter())->classPages(new ReportSite($catalog), new PageShell());

        self::assertSame(['classes/app-r.html'], array_keys($pages));
        self::assertStringContainsString('<span class="crumb-current">R</span>', $pages['classes/app-r.html']);
    }

    public function testFilePagesWriteOneDocumentPerFile(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $pages = (new HtmlReporter())->filePages(new ReportSite($catalog), new PageShell());

        self::assertSame(['files/src-a-php.html'], array_keys($pages));
        self::assertStringContainsString('<title>src/a.php — SQL catalog</title>', $pages['files/src-a-php.html']);
    }

    public function testStatementPagesWriteOneDocumentPerStatement(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
        ]);
        $pages = (new HtmlReporter())->statementPages(new ReportSite($catalog), new PageShell());

        self::assertSame(['statements/a1.html', 'statements/b1.html'], array_keys($pages));
        self::assertStringContainsString('<title>SELECT at b.php:1 — SQL catalog</title>', $pages['statements/b1.html']);
    }
}
