<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\StatementRow;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(StatementList::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(Palette::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class StatementListTest extends TestCase
{
    public function testRowsListEveryStatementAndSaySoWhenThereIsNone(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 4, 'f', 'pdo.query'), []);
        $site = new ReportSite(new Catalog([$entry]));
        $list = new StatementList();

        self::assertStringStartsWith('<ol class="rows"><li class="row" data-kind="select"', $list->rows($site, 'index.html', [$entry]));
        self::assertSame('<p class="none">No statement here.</p>', $list->rows($site, 'index.html', []));
    }

    public function testFacetsOfferOnlyWhatWouldNarrowTheListing(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'x')]),
        ];

        self::assertSame(
            '<div class="facets"><input type="search" class="facet-search" placeholder="Narrow by text…" autocomplete="off" spellcheck="false">'
            . '<span class="facet-group"><button type="button" class="chip facet k-select" data-facet="kind" data-value="select">SELECT<span class="facet-count">1</span></button>'
            . '<button type="button" class="chip facet k-insert" data-facet="kind" data-value="insert">INSERT<span class="facet-count">1</span></button></span>'
            . '<span class="facet-shown" data-total="2"></span><button type="button" class="facet-clear" hidden>Clear</button></div>',
            (new StatementList())->facets($entries),
        );
    }

    public function testGroupIsSilentWhenEveryRowSharesTheValue(): void
    {
        self::assertSame('', (new StatementList())->group('kind', ['select' => 3], static fn (string $value): string => 'k-' . $value));
    }
}
