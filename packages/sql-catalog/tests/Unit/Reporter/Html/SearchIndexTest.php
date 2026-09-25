<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Catalog\Resolution;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SearchIndex;

#[CoversClass(SearchIndex::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Scope::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\StatementPart::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\SqlHighlighter::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Core\Text\Origin::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
final class SearchIndexTest extends TestCase
{
    public function testRenderWritesTheIndexAsAScriptThePagesLoad(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            'window.__CATALOG_INDEX__ = [{"q":"SELECT 1","w":"a.php:1","f":"f","t":"","u":"statements/a1.html","k":"SELECT","r":"resolved"}];' . "\n",
            (new SearchIndex())->render(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter())),
        );
        self::assertSame("window.__CATALOG_INDEX__ = [];\n", (new SearchIndex())->render(new ReportSite(new Catalog(), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter())));
    }

    public function testEntryToArrayCarriesWhatTheSearchReads(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Delete, TextPattern::fromText("DELETE FROM users\n WHERE id = 1"), ['users'], [], new CallSite('a.php', 3, 'App\\R::gone', 'pdo.query'), []);

        self::assertSame(
            ['q' => 'DELETE FROM users WHERE id = 1', 'w' => 'a.php:3', 'f' => 'App\\R::gone', 't' => 'users', 'u' => 'statements/a1.html', 'k' => 'DELETE', 'r' => 'resolved'],
            (new SearchIndex())->entryToArray(new ReportSite(new Catalog([$entry]), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), $entry),
        );
    }
    public function testEntryToArrayMakesTheVariableNameSearchable(): void
    {
        $entry = new CatalogEntry('q', StatementKind::Unknown, TextPattern::fromHole(
            new \SqlCatalog\Core\Text\TextHole(\SqlCatalog\Core\Text\Origin::Parameter, \SqlCatalog\Core\Type\TypeShape::unknown(), '$sql'),
        ), [], [], new CallSite('query.php', 4, 'f', 'pdo.prepare'), []);

        self::assertSame('{$sql}', (new SearchIndex())->entryToArray(new ReportSite(new Catalog([$entry]), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), $entry)['q']);
    }

}
