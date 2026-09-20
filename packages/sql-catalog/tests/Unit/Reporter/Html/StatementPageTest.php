<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementCard;
use SqlCatalog\Reporter\Html\StatementPage;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(StatementPage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementCard::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(\SqlCatalog\Catalog\StatementPart::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class StatementPageTest extends TestCase
{
    public function testRenderListsTheStatementsOfThePage(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $page = (new StatementPage())->render(new ReportSite($catalog), 1);

        self::assertStringContainsString('<h1>Statements <span class="count">page 1 of 1</span></h1>', $page);
        self::assertStringContainsString('1 statement from 1 file.', $page);
        self::assertStringContainsString('id="a1"', $page);
    }

    public function testRenderSaysSoWhenThereIsNothingToList(): void
    {
        self::assertStringContainsString(
            'No statement was found.',
            (new StatementPage())->render(new ReportSite(new Catalog()), 1),
        );
    }

    public function testGroupPutsAFilesStatementsUnderItsName(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);
        $group = (new StatementPage())->group(new ReportSite(new Catalog()), 'src/a.php', [$entry]);

        self::assertStringContainsString('<section class="file-group" id="file-src-a-php">', $group);
        self::assertStringContainsString('<h2>src/a.php<span class="count">1 statement</span>', $group);
    }

    public function testPagerIsSilentWhenThereIsOnlyOnePage(): void
    {
        self::assertSame('', (new StatementPage())->pager(new ReportSite(new Catalog()), 1));
    }

    public function testPagerLinksToThePagesEitherSide(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            '<nav class="pager"><span class="is-disabled">‹ Previous</span>'
            . '<span class="is-current">1</span><a href="../statements/page-2.html">2</a>'
            . '<a href="../statements/page-2.html">Next ›</a>'
            . '<span class="pager-note">page 1 of 2</span></nav>',
            (new StatementPage())->pager(new ReportSite($catalog, 1), 1),
        );
    }

    public function testStepIsWrittenAsALinkOnlyWhenThereIsSomewhereToStep(): void
    {
        $page = new StatementPage();
        $site = new ReportSite(new Catalog());

        self::assertSame('<span class="is-disabled">Next ›</span>', $page->step($site, '', 2, 'Next ›'));
        self::assertSame('<a href="statements/page-1.html">Next ›</a>', $page->step($site, '', 1, 'Next ›'));
    }

    /**
     * @return list<array{int, int, list<int|null>}>
     */
    public static function providerWindowOf(): array
    {
        return [
            [1, 1, [1]],
            [1, 5, [1, 2, 3, null, 5]],
            [1, 12, [1, 2, 3, null, 12]],
            [6, 12, [1, null, 4, 5, 6, 7, 8, null, 12]],
            [12, 12, [1, null, 10, 11, 12]],
        ];
    }

    /**
     * @param list<int|null> $expected
     */
    #[DataProvider('providerWindowOf')]
    public function testWindowOfShowsThePagesAroundTheOneBeingRead(int $number, int $count, array $expected): void
    {
        self::assertSame($expected, (new StatementPage())->windowOf($number, $count));
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

        self::assertSame(
            '<h1>Statements <span class="count">page 1 of 1</span></h1><p class="lede">2 statements from 2 files.</p><section class="file-group" id="file'
                . '-src-a-php"><h2>src/a.php<span class="count">1 statement</span><a class="anchor" href="#file-src-a-php">#</a></h2><article class="stmt" id="'
                . 'a1" data-severity="high"><header class="stmt-head"><span class="chip k-select">SELECT</span><span class="chip s-danger" title="The values we'
                . 're followed to runtime input, so the text cannot be fixed.">external-input</span><span class="chip s-danger">high</span><span class="stmt-si'
                . 'te">src/a.php:4</span><span class="stmt-fn">R::find</span><a class="anchor" href="#a1" title="a1">#</a></header><pre class="sql"><span class'
                . '="tok-kw">SELECT</span> id <span class="tok-kw">FROM</span> posts <span class="tok-kw">WHERE</span> slug = <span class="hole hole-external" '
                . 'title="This is a gap: external input fills it. Written as $_GET[&quot;s&quot;].">{$}</span></pre><div class="stmt-tables"><span class="chip '
                . 'chip-ghost">posts</span> <span class="chip chip-sm" title="The database call that was matched">pdo.query</span></div><div class="notice noti'
                . 'ce-warn"><ul><li>Assembled from parts that vary independently, so some of these may be unreachable.</li><li>Read through R::find → R::run.</'
                . 'li></ul></div><div class="stmt-blocks"><section class="stmt-block"><h4>Values</h4><div class="table-wrap"><table><thead><tr><th class="tight'
                . '">Parameter</th><th class="tight">Type</th><th>Bound to</th></tr></thead><tbody><tr><td class="tight"><code>?</code></td><td class="tight"><'
                . 'code>int</code></td><td><code>7</code></td></tr></tbody></table></div></section><section class="stmt-block"><h4>Findings</h4><ul class="find'
                . 'ing-list"><li><span class="chip s-danger">high</span><span>spliced</span></li></ul></section></div></article></section><section class="file-'
                . 'group" id="file-src-b-php"><h2>src/b.php<span class="count">1 statement</span><a class="anchor" href="#file-src-b-php">#</a></h2><article cl'
                . 'ass="stmt" id="b1" data-severity="info"><header class="stmt-head"><span class="chip k-insert">INSERT</span><span class="chip s-ok" title="Th'
                . 'e statement text is fully determined.">resolved</span><span class="stmt-site">src/b.php:9</span><span class="stmt-fn">R::add</span><a class='
                . '"anchor" href="#b1" title="b1">#</a></header><pre class="sql"><span class="tok-kw">INSERT</span> <span class="tok-kw">INTO</span> posts (id)'
                . ' <span class="tok-kw">VALUES</span> (<span class="tok-num">1</span>)</pre><div class="stmt-tables"><span class="chip chip-ghost">posts</span'
                . '> <span class="chip chip-sm" title="The database call that was matched">pdo.query</span></div><div class="stmt-blocks"></div></article></sec'
                . 'tion>',
            (new StatementPage())->render(new ReportSite($catalog), 1),
        );
    }
}
