<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\TableIndexPage;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\TableName;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(TableIndexPage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(TableName::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class TableIndexPageTest extends TestCase
{
    public function testRenderGroupsTablesBySchemaWhenAnyIsQualified(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'app.orders'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $page = (new TableIndexPage())->render(new ReportSite($catalog));

        self::assertStringContainsString('<h1>Tables<span class="count">2 tables</span></h1>', $page);
        self::assertStringContainsString('<h2 id="schema-unqualified">Unqualified<span class="count">1 table</span></h2>', $page);
        self::assertStringContainsString('<h2 id="schema-app">app<span class="count">1 table</span></h2>', $page);
        self::assertStringContainsString('No statement names a table.', (new TableIndexPage())->render(new ReportSite(new Catalog())));
    }

    public function testRenderDoesNotGroupWhenNoTableIsQualified(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertStringNotContainsString('Unqualified', (new TableIndexPage())->render(new ReportSite($catalog)));
    }

    public function testBySchemaPutsUnqualifiedTablesUnderAnEmptyName(): void
    {
        self::assertSame(['', 'app'], array_keys((new TableIndexPage())->bySchema(['app.orders' => [], 'users' => []])));
    }

    public function testTableIsSortableByEveryColumn(): void
    {
        $table = (new TableIndexPage())->table(new ReportSite(new Catalog()), []);

        self::assertStringContainsString('<table class="sortable filter-target"><thead><tr><th data-sort="text">Table</th><th class="num" data-sort="num">Statements</th>', $table);
    }

    public function testRowCountsHowTheTableIsUsedAndMarksAGapInItsName(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['{$}users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'x')]),
            new CatalogEntry('a2', StatementKind::Alter, TextPattern::fromText('ALTER'), ['{$}users'], [], new CallSite('a.php', 2, 'g', 'pdo.query'), []),
        ];
        $site = new ReportSite(new Catalog($entries));

        self::assertSame(
            '<tr><td><a class="mono" href="tables/users.html"><span class="hole hole-open" title="A part of this name the analysis could not pin down">{$}</span>users</a></td>'
            . '<td class="num">2</td><td class="num">1</td><td class="num"><span class="none">0</span></td><td class="num">1</td><td class="num">1</td><td class="num">2</td></tr>',
            (new TableIndexPage())->row($site, '{$}users', $entries),
        );
    }
}
