<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
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
use SqlCatalog\Reporter\CatalogArtifacts;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\FindingPage;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\OverviewPage;
use SqlCatalog\Reporter\Html\PageShell;
use SqlCatalog\Reporter\Html\ReportAssets;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\SearchIndex;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementCard;
use SqlCatalog\Reporter\Html\StatementPage;
use SqlCatalog\Reporter\Html\TablePage;
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
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(FindingPage::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(Origin::class)]
#[UsesClass(OverviewPage::class)]
#[UsesClass(PageShell::class)]
#[UsesClass(ReportAssets::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(SearchIndex::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementCard::class)]
#[UsesClass(StatementPage::class)]
#[UsesClass(TablePage::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Catalog\StatementPart::class)]
#[UsesClass(StatementKind::class)]
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
        $artifacts = (new HtmlReporter())->render(new Catalog());

        self::assertSame(
            [
                'assets/report.css',
                'assets/report.js',
                'assets/search-index.js',
                'findings.html',
                'index.html',
                'statements/page-1.html',
                'tables.html',
            ],
            $artifacts->names(),
        );
    }

    public function testRenderNamesThePageAReaderOpensFirst(): void
    {
        self::assertSame(
            (new HtmlReporter())->render(new Catalog())->get(HtmlReporter::FILE),
            (new HtmlReporter())->render(new Catalog())->primary(),
        );
    }

    public function testRenderSplitsTheStatementsAcrossPages(): void
    {
        $entries = array_map(
            static fn (int $line): CatalogEntry => new CatalogEntry(
                'id' . $line,
                StatementKind::Select,
                TextPattern::fromText('SELECT ' . $line),
                [],
                [],
                new CallSite('file' . $line . '.php', 1, 'f', 'pdo.query'),
                [],
            ),
            range(1, 120),
        );
        $names = (new HtmlReporter())->render(new Catalog($entries))->names();

        self::assertContains('statements/page-3.html', $names);
        self::assertNotContains('statements/page-4.html', $names);
    }

    public function testRenderLinksTheOverviewToWhereAStatementIs(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT * FROM posts'), ['posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ]),
        ]);
        $artifacts = (new HtmlReporter())->render($catalog);

        self::assertStringContainsString('statements/page-1.html#file-a-php', (string) $artifacts->get('index.html'));
        self::assertStringContainsString('statements/page-1.html#a1', (string) $artifacts->get('tables.html'));
        self::assertStringContainsString('statements/page-1.html#a1', (string) $artifacts->get('findings.html'));
        self::assertStringContainsString('"u":"statements/page-1.html#a1"', (string) $artifacts->get('assets/search-index.js'));
    }

    public function testRenderKeepsACallNoStatementWasReadFromApartFromAStatement(): void
    {
        $catalog = new Catalog([
            new CatalogEntry(
                'a1',
                StatementKind::Unknown,
                TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')),
                [],
                [],
                new CallSite('a.php', 1, 'f', CallSite::UNREACHED),
                [],
            ),
        ]);
        $page = (string) (new HtmlReporter())->render($catalog)->get('statements/page-1.html');

        self::assertStringContainsString('no statement was read from this call', $page);
        self::assertStringContainsString('<span class="chip s-neutral" title="The call was found but never examined', $page);
    }

    public function testRenderCarriesTheFilesThatCouldNotBeRead(): void
    {
        $overview = (string) (new HtmlReporter())
            ->render(new Catalog([], [new AnalysisProblem('b.php', 'broken')]))
            ->get('index.html');

        self::assertStringContainsString('<tr><td><code>b.php</code></td><td>broken</td></tr>', $overview);
    }

    public function testStatementPagesWriteOneDocumentPerPage(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
        ]);
        $pages = (new HtmlReporter())->statementPages(new ReportSite($catalog, 1), new PageShell());

        self::assertSame(['statements/page-1.html', 'statements/page-2.html'], array_keys($pages));
        self::assertStringContainsString('<title>Statements, page 2 — SQL catalog</title>', $pages['statements/page-2.html']);
    }
}
