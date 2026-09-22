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
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\FindingPage;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\StatementRow;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(FindingPage::class)]
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
#[UsesClass(StatementList::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class FindingPageTest extends TestCase
{
    public function testRenderOpensWithWhereToLookAndThenListsEveryRule(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
                Finding::of(FindingRule::AnalysisIncomplete, 'stopped'),
            ]),
        ]);
        $page = (new FindingPage())->render(new ReportSite($catalog));

        self::assertStringContainsString('<h1>Findings<span class="count">2 findings</span></h1>', $page);
        self::assertStringContainsString('<h2 id="hotspots">Where to look first</h2>', $page);
        self::assertStringContainsString('<section class="group" id="rule-external-input">', $page);
        self::assertStringContainsString('<section class="group" id="rule-analysis-incomplete">', $page);
        self::assertStringContainsString('Nothing was reported', (new FindingPage())->render(new ReportSite(new Catalog())));
    }

    public function testHotspotsLinkEachFunctionAndAreSilentWhenNothingIsFlagged(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'spliced'),
            ]),
        ]);

        self::assertStringContainsString(
            '<tr><td><a class="mono" href="classes/app-r.html#fn-app-r-find">R::find</a></td><td><a class="muted" href="files/src-a-php.html">src/a.php</a></td>'
            . '<td class="num"><span class="none">0</span></td><td class="num">1</td></tr>',
            (new FindingPage())->hotspots(new ReportSite($catalog)),
        );
        self::assertSame('', (new FindingPage())->hotspots(new ReportSite(new Catalog())));
    }

    public function testSectionHeadsTheRuleWithItsSeverityAndMeaning(): void
    {
        $section = (new FindingPage())->section(new ReportSite(new Catalog()), FindingRule::DynamicSql, []);

        self::assertStringStartsWith(
            '<section class="group" id="rule-dynamic-sql"><h2><code>dynamic-sql</code><span class="chip s-warn">medium</span><span class="count">0 statements</span>',
            $section,
        );
        self::assertStringContainsString('A value is spliced into the statement text instead of being bound.', $section);
    }

    public function testAnchorsNameTheHotspotsAndEveryRule(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'spliced'),
            ]),
        ]);

        self::assertSame([['Where to look first', 'hotspots'], ['dynamic-sql', 'rule-dynamic-sql']], (new FindingPage())->anchors(new ReportSite($catalog)));
    }
}
