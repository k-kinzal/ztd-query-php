<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\FindingPage;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementCard;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(FindingPage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementCard::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class FindingPageTest extends TestCase
{
    public function testRenderSaysSoWhenThereIsNothingToReport(): void
    {
        $catalog = new Catalog();

        self::assertStringContainsString(
            'Nothing was reported about any statement.',
            (new FindingPage())->render(new ReportSite($catalog), $catalog, new CatalogStatistics($catalog)),
        );
    }

    public function testRenderGathersTheFindingsUnderTheirRule(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ]),
        ]);
        $page = (new FindingPage())->render(new ReportSite($catalog), $catalog, new CatalogStatistics($catalog));

        self::assertStringContainsString('<h1>Findings <span class="count">1 finding</span></h1>', $page);
        self::assertStringContainsString('id="rule-external-input"', $page);
        self::assertStringContainsString('spliced', $page);
    }

    public function testGroupCollectsWhatEachRuleReported(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'one'),
                Finding::of(FindingRule::ExternalInput, 'two'),
            ]),
        ]);

        self::assertSame(['dynamic-sql', 'external-input'], array_keys((new FindingPage())->group($catalog)));
    }

    public function testSectionExplainsWhatTheRuleReports(): void
    {
        $section = (new FindingPage())->section(new ReportSite(new Catalog()), FindingRule::CallNotAnalyzed, 3, []);

        self::assertStringContainsString('<h2 id="rule-call-not-analyzed">', $section);
        self::assertStringContainsString('<span class="count">3</span>', $section);
        self::assertStringContainsString('never examined', $section);
    }

    public function testRowLinksToTheStatementTheFindingIsAbout(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Update, TextPattern::fromText('UPDATE t SET a = 1'), [], [], new CallSite('a.php', 9, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            '<tr><td class="tight"><span class="chip k-update">UPDATE</span></td>'
            . '<td><a class="stmt-link" href="statements/page-1.html#a1"><code>UPDATE t SET a = 1</code></a></td>'
            . '<td>spliced</td><td class="tight"><span class="stmt-site">a.php:9</span></td></tr>',
            (new FindingPage())->row(new ReportSite($catalog), $catalog->entries()[0], Finding::of(FindingRule::DynamicSql, 'spliced')),
        );
    }

    public function testRenderIsWrittenExactly(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromSegments([
                new LiteralText('SELECT id FROM posts WHERE slug = '),
                new TextHole(Origin::External, TypeShape::unknown(), '$_GET["s"]'),
            ]), ['posts'], [new Placeholder('?', 0, null, new ValueDomain('int', [7], true, []))], new CallSite('src/a.php', 4, 'R::find', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ], false, ['R::find', 'R::run']),
            new CatalogEntry('b1', StatementKind::Insert, TextPattern::fromText('INSERT INTO posts (id) VALUES (1)'), ['posts'], [], new CallSite('src/b.php', 9, 'R::add', 'pdo.query'), []),
        ];
        $catalog = new Catalog($entries, [new AnalysisProblem('src/broken.php', 'broken')]);

        $site = new ReportSite($catalog);

        self::assertSame(
            '<h1>Findings <span class="count">1 finding</span></h1><p class="lede">A finding is a judgement about a statement the analyzer read. What sto'
                . 'pped the analysis is reported here too, so a gap in the catalog is never silent.</p><h2 id="rule-external-input"><code>external-input</code>'
                . '<span class="chip s-danger">high</span><span class="count">1</span><a class="anchor" href="#rule-external-input">#</a></h2><p class="lede">A'
                . ' value spliced into the statement text comes from external input.</p><div class="table-wrap"><table><thead><tr><th class="tight">Kind</th><t'
                . 'h>Statement</th><th>What was found</th><th class="tight">Where</th></tr></thead><tbody><tr><td class="tight"><span class="chip k-select">SEL'
                . 'ECT</span></td><td><a class="stmt-link" href="statements/page-1.html#a1"><code>SELECT id FROM posts WHERE slug = {$}</code></a></td><td>spli'
                . 'ced</td><td class="tight"><span class="stmt-site">src/a.php:4</span></td></tr></tbody></table></div>',
            (new FindingPage())->render($site, $catalog, new CatalogStatistics($catalog)),
        );
    }
}
