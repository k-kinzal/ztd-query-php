<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\FileIndexPage;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(FileIndexPage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class FileIndexPageTest extends TestCase
{
    public function testRenderListsFilesUnderTheirDirectories(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('index.php', 1, 'f', 'pdo.query'), []),
        ]);
        $page = (new FileIndexPage())->render(new ReportSite($catalog));

        self::assertStringContainsString('<h1>Files<span class="count">2 files</span></h1>', $page);
        self::assertStringContainsString('<h2 id="dir-root"><code>(root)</code><span class="count">1 file</span></h2>', $page);
        self::assertStringContainsString('<h2 id="dir-src"><code>src/</code><span class="count">1 file</span></h2>', $page);
        self::assertStringContainsString('No statement was found.', (new FileIndexPage())->render(new ReportSite(new Catalog())));
    }

    public function testSectionIsASortableTableOfTheDirectorysFiles(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $section = (new FileIndexPage())->section(new ReportSite($catalog), 'src', ['src/a.php']);

        self::assertStringContainsString('<table class="sortable filter-target"><thead><tr><th data-sort="text">File</th>', $section);
        self::assertStringContainsString('href="files/src-a-php.html">a.php</a>', $section);
    }

    public function testRowCountsWhatWasFoundInTheFile(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), ['users'], [], new CallSite('src/a.php', 2, 'g', 'pdo.query'), []),
        ];

        self::assertSame(
            '<tr><td><a class="mono" href="files/src-a-php.html">a.php</a></td><td class="num">2</td><td class="num">2</td><td class="num">1</td><td class="num"><span class="none">0</span></td></tr>',
            (new FileIndexPage())->row(new ReportSite(new Catalog($entries)), 'src/a.php', $entries),
        );
    }

    public function testAnchorsNameEveryDirectory(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame([['src/', 'dir-src']], (new FileIndexPage())->anchors(new ReportSite($catalog)));
    }
}
