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
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementCard;
use SqlCatalog\Reporter\Html\TablePage;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(TablePage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementCard::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class TablePageTest extends TestCase
{
    public function testRenderSaysSoWhenNoStatementNamesATable(): void
    {
        $catalog = new Catalog();

        self::assertStringContainsString(
            'No statement names a table.',
            (new TablePage())->render(new ReportSite($catalog), $catalog, new CatalogStatistics($catalog)),
        );
    }

    public function testRenderListsEveryTableWithWhatNamesIt(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT * FROM posts'), ['posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT INTO posts VALUES (1)'), ['posts'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);
        $page = (new TablePage())->render(new ReportSite($catalog), $catalog, new CatalogStatistics($catalog));

        self::assertStringContainsString('<h1>Tables <span class="count">1 table</span></h1>', $page);
        self::assertStringContainsString('2 statements · 1 read · 1 written', $page);
        self::assertStringContainsString('statements/page-1.html#a1', $page);
    }

    public function testGroupCollectsTheStatementsNamingEachTable(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['posts', 'users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['posts', 'users'], array_keys((new TablePage())->group($catalog)));
    }

    public function testSectionSaysHowTheTableIsUsed(): void
    {
        $section = (new TablePage())->section(
            new ReportSite(new Catalog()),
            ['name' => 'wp_posts', 'reads' => 3, 'writes' => 1, 'statements' => 4],
            [],
        );

        self::assertStringContainsString('<details class="usage" id="table-wp-posts">', $section);
        self::assertStringContainsString('4 statements · 3 read · 1 written', $section);
    }

    public function testRowLinksToTheStatementItStandsFor(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 7, 'f', 'pdo.query'), []),
        ]);
        $entry = $catalog->entries()[0];

        self::assertSame(
            '<tr><td class="tight"><span class="chip k-select">SELECT</span></td>'
            . '<td><a class="stmt-link" href="statements/page-1.html#a1"><code>SELECT 1</code></a></td>'
            . '<td class="tight"><span class="stmt-site">a.php:7</span></td></tr>',
            (new TablePage())->row(new ReportSite($catalog), $entry),
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
            '<h1>Tables <span class="count">1 table</span></h1><p class="lede">Every table the statements name. A table is counted once per statement tha'
                . 't names it, so a join counts for each of its tables.</p><details class="usage" id="table-posts"><summary><code>posts</code><span class="coun'
                . 't">2 statements · 1 read · 1 written</span></summary><div class="table-wrap"><table><thead><tr><th class="tight">Kind</th><th>Statement</th>'
                . '<th class="tight">Where</th></tr></thead><tbody><tr><td class="tight"><span class="chip k-select">SELECT</span></td><td><a class="stmt-link"'
                . ' href="statements/page-1.html#a1"><code>SELECT id FROM posts WHERE slug = {$}</code></a></td><td class="tight"><span class="stmt-site">src/a'
                . '.php:4</span></td></tr><tr><td class="tight"><span class="chip k-insert">INSERT</span></td><td><a class="stmt-link" href="statements/page-1.'
                . 'html#b1"><code>INSERT INTO posts (id) VALUES (1)</code></a></td><td class="tight"><span class="stmt-site">src/b.php:9</span></td></tr></tbod'
                . 'y></table></div></details>',
            (new TablePage())->render($site, $catalog, new CatalogStatistics($catalog)),
        );
    }
}
