<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\SearchIndex;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementCard;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\TextPattern;

#[CoversClass(SearchIndex::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementCard::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
final class SearchIndexTest extends TestCase
{
    public function testRenderWritesAnEmptyIndexForAnEmptyCatalog(): void
    {
        self::assertSame(
            'window.__CATALOG_INDEX__ = [];' . "\n",
            (new SearchIndex())->render(new ReportSite(new Catalog()), new Catalog()),
        );
    }

    public function testRenderWritesTheIndexAsAScriptThePagesLoad(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertStringStartsWith(
            'window.__CATALOG_INDEX__ = [{"q":"SELECT 1"',
            (new SearchIndex())->render(new ReportSite($catalog), $catalog),
        );
    }

    public function testEntryToArrayCarriesWhatTheSearchReads(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Delete, TextPattern::fromText('DELETE FROM posts'), ['posts'], [], new CallSite('a.php', 4, 'App\\R::wipe', 'pdo.query'), []),
        ]);

        self::assertSame(
            [
                'q' => 'DELETE FROM posts',
                'w' => 'a.php:4 App\\R::wipe',
                't' => 'posts',
                'u' => 'statements/page-1.html#a1',
                'k' => 'DELETE',
                'g' => 'delete',
                'r' => 'resolved',
                'c' => 'ok',
            ],
            (new SearchIndex())->entryToArray(new ReportSite($catalog), $catalog->entries()[0]),
        );
    }
}
