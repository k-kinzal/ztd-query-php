<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\AnalysisProblem;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Catalog\Finding;
use SqlCatalog\Core\Catalog\FindingRule;
use SqlCatalog\Core\Catalog\Resolution;
use SqlCatalog\Core\Catalog\Severity;
use SqlCatalog\Core\Catalog\StatementPart;
use SqlCatalog\Core\Reporter\CatalogArtifacts;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlReporter;
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
use SqlCatalog\Reporter\Html\Source\SourceCode;
use SqlCatalog\Reporter\Html\SqlFormatter;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\StatementRow;
use SqlCatalog\Reporter\Html\TableName;

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
#[UsesClass(SourceCode::class)]
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
        self::assertSame('html', (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->name());
    }

    public function testDescriptionMentionsWhatItProduces(): void
    {
        self::assertStringContainsString('HTML', (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->description());
    }

    public function testRenderWritesTheWholeSiteOfPages(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT * FROM posts'), ['posts'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ]),
        ]);
        $artifacts = (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->render($catalog);

        self::assertSame(
            [
                'assets/document-design-LICENSE.txt',
                'assets/document-design-v1.0.0.css',
                'assets/document-design-v1.0.0.js',
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
        $artifacts = (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->render(new Catalog());

        self::assertSame($artifacts->get(HtmlReporter::FILE), $artifacts->primary());
        self::assertStringContainsString('<title>Overview</title>', (string) $artifacts->primary());
    }

    public function testRenderLinksEveryRouteToTheStatementsPage(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT * FROM posts'), ['posts'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ]),
        ]);
        $artifacts = (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->render($catalog);

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
        $page = (string) (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->render($catalog)->get('statements/a1.html');

        self::assertStringContainsString('no statement was read from this call', $page);
        self::assertStringContainsString('<span class="chip tone-neutral">not-analyzed</span>', $page);
    }

    public function testRenderCarriesTheFilesThatCouldNotBeRead(): void
    {
        $overview = (string) (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->render(new Catalog([], [new AnalysisProblem('b.php', 'broken')]))->get('index.html');

        self::assertStringContainsString('<tr><td><code>b.php</code></td><td>broken</td></tr>', $overview);
    }

    public function testTablePagesWriteOneDocumentPerTable(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $pages = (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->tablePages(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), new PageShell());

        self::assertSame(['tables/posts.html', 'tables/users.html'], array_keys($pages));
        self::assertStringContainsString('<title>posts</title>', $pages['tables/posts.html']);
    }

    public function testClassPagesWriteOneDocumentPerClass(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'helper', 'pdo.query'), []),
        ]);
        $pages = (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->classPages(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), new PageShell());

        self::assertSame(['classes/app-r.html'], array_keys($pages));
        self::assertStringContainsString('<span class="breadcrumb-current">R</span>', $pages['classes/app-r.html']);
    }

    public function testFilePagesWriteOneDocumentPerFile(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $pages = (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->filePages(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), new PageShell());

        self::assertSame(['files/src-a-php.html'], array_keys($pages));
        self::assertStringContainsString('<title>src/a.php</title>', $pages['files/src-a-php.html']);
    }

    public function testStatementPagesWriteOneDocumentPerStatement(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
        ]);
        $pages = (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->statementPages(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), new PageShell());

        self::assertSame(['statements/a1.html', 'statements/b1.html'], array_keys($pages));
        self::assertStringContainsString('<title>SELECT at b.php:1</title>', $pages['statements/b1.html']);
    }

    public function testRenderWritesTheTrailToEveryPage(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT * FROM posts'), ['posts'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), []),
        ]);
        $artifacts = (new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter()))->render($catalog);
        $home = '<a href="../index.html">Overview</a><span class="breadcrumb-sep">/</span>';

        self::assertStringContainsString('<nav class="breadcrumbs" aria-label="Breadcrumb"><span class="breadcrumb-current">Overview</span></nav>', (string) $artifacts->get('index.html'));
        self::assertStringContainsString('<a href="index.html">Overview</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Statements</span>', (string) $artifacts->get('statements.html'));
        self::assertStringContainsString('<a href="index.html">Overview</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Tables</span>', (string) $artifacts->get('tables.html'));
        self::assertStringContainsString('<a href="index.html">Overview</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Namespaces</span>', (string) $artifacts->get('namespaces.html'));
        self::assertStringContainsString('<a href="index.html">Overview</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Files</span>', (string) $artifacts->get('files.html'));
        self::assertStringContainsString('<a href="index.html">Overview</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Findings</span>', (string) $artifacts->get('findings.html'));
        self::assertStringContainsString($home . '<a href="../tables.html">Tables</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">posts</span>', (string) $artifacts->get('tables/posts.html'));
        self::assertStringContainsString($home . '<a href="../namespaces.html">Namespaces</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">R</span>', (string) $artifacts->get('classes/app-r.html'));
        self::assertStringContainsString($home . '<a href="../files.html">Files</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">src/a.php</span>', (string) $artifacts->get('files/src-a-php.html'));
        self::assertStringContainsString($home . '<a href="../statements.html">Statements</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">a1</span>', (string) $artifacts->get('statements/a1.html'));
        self::assertStringContainsString('<a href="#attention" title="Needs attention">Needs attention</a></li><li><a href="#coverage"', (string) $artifacts->get('index.html'));
        self::assertStringContainsString('<p class="sidebar-title">Tables</p>', (string) $artifacts->get('tables/posts.html'));
        self::assertStringContainsString('<p class="sidebar-title">Classes in App</p>', (string) $artifacts->get('classes/app-r.html'));
        self::assertStringContainsString('<p class="sidebar-title">Files in src/</p>', (string) $artifacts->get('files/src-a-php.html'));
        self::assertStringContainsString('<p class="sidebar-title">Belongs to</p>', (string) $artifacts->get('statements/a1.html'));
    }

    #[DataProvider('providerOpenOrigins')]
    public function testRenderIncludesSourceForUnresolvedAndUnanalyzedCallsAndParseFailures(Origin $origin): void
    {
        $entry = new CatalogEntry($origin->value, StatementKind::Unknown, TextPattern::fromHole(new TextHole($origin, TypeShape::unknown(), '$db->query($sql)')), [], [], new CallSite('src/query.php', 3, 'f', 'unmatched'), []);
        $catalog = new Catalog([$entry], [new AnalysisProblem('src/broken.php', 'Unexpected <token>')], [
            'src/query.php' => "<?php\n\$sql = buildQuery();\n\$db->query(\$sql);",
            'src/broken.php' => '<?php function { <script>alert(1)</script>',
        ]);
        $reporter = new HtmlReporter(\SqlCatalog\Facade\Builtins::sqlFormatter());
        $artifacts = $reporter->render($catalog->filter(static fn (CatalogEntry $entry): bool => true));
        $page = (string) $artifacts->get('statements/' . $entry->id . '.html');
        self::assertStringContainsString('$sql = buildQuery();', $page);
        self::assertStringContainsString('$db-&gt;query($sql);', $page);
        self::assertStringContainsString('class="code-line is-target" id="L3"', $page);
        self::assertStringContainsString('href="../files/src-query-php.html#L3">View full source</a>', $page);
        $file = (string) $artifacts->get('files/src-query-php.html');
        self::assertStringContainsString('href="#source" title="Source code">Source code</a>', $file);
        self::assertStringContainsString('id="L3"', $file);
        $broken = (string) $artifacts->get('files/src-broken-php.html');
        self::assertStringContainsString('Unexpected &lt;token&gt;', $broken);
        self::assertStringContainsString('&lt;?php function { &lt;script&gt;', $broken);
        self::assertStringNotContainsString('<script>alert(1)</script>', $broken);
        self::assertStringContainsString('href="files/src-broken-php.html#source"', (string) $artifacts->get('index.html'));
        self::assertStringContainsString('href="files/src-broken-php.html"', (string) $artifacts->get('files.html'));
        self::assertEquals($artifacts, $reporter->render($catalog));
    }


    /**
     * @return iterable<string, array{Origin}>
     */
    public static function providerOpenOrigins(): iterable
    {
        yield 'unresolved' => [Origin::Call];
        yield 'not analyzed' => [Origin::Unreached];
    }

}
