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
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementCard;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(StatementCard::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(Origin::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(\SqlCatalog\Catalog\StatementPart::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(StatementKind::class)]
final class StatementCardTest extends TestCase
{
    public function testRenderCarriesEverythingKnownAboutTheStatement(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT id FROM users WHERE id = ?'),
            ['users'],
            [new Placeholder('?', 0, null, new ValueDomain('int', [7], true, []))],
            new CallSite('a.php', 12, 'App\\R::find', 'pdo.prepare'),
            [],
        );
        $card = (new StatementCard())->render($entry);

        self::assertStringContainsString('<article class="stmt" id="abc" data-severity="info">', $card);
        self::assertStringContainsString('SELECT', $card);
        self::assertStringContainsString('a.php:12', $card);
        self::assertStringContainsString('users', $card);
        self::assertStringContainsString('<code>7</code>', $card);
    }

    public function testHeadNamesWhatTheStatementIsAndWhereItIsIssued(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Insert,
            TextPattern::fromText('INSERT INTO t VALUES (1)'),
            [],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [],
        );

        self::assertSame(
            '<header class="stmt-head"><span class="chip k-insert">INSERT</span>'
            . '<span class="chip s-ok" title="The statement text is fully determined.">resolved</span>'
            . '<span class="stmt-site">a.php:3</span><span class="stmt-fn">f</span>'
            . '<a class="anchor" href="#abc" title="abc">#</a></header>',
            (new StatementCard())->head($entry),
        );
    }

    public function testHeadShowsTheSeverityWhenThereIsSomethingToLookAt(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [Finding::of(FindingRule::ExternalInput, 'x')],
        );

        self::assertStringContainsString('<span class="chip s-danger">high</span>', (new StatementCard())->head($entry));
    }

    public function testBodyWritesTheStatement(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [],
        );

        self::assertSame(
            '<pre class="sql"><span class="tok-kw">SELECT</span> <span class="tok-num">1</span></pre>',
            (new StatementCard())->body($entry),
        );
    }

    public function testBodyDoesNotDressUpACallNoStatementWasReadFrom(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Unknown,
            TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')),
            [],
            [],
            new CallSite('a.php', 3, 'f', CallSite::UNREACHED),
            [],
        );

        self::assertSame(
            '<pre class="sql"><span class="tok-com">-- no statement was read from this call</span>'
            . "\n" . '$db-&gt;query($sql)</pre>',
            (new StatementCard())->body($entry),
        );
    }

    public function testTablesNameWhatTheStatementTouchesAndHowItGotThere(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            ['users'],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [],
        );

        self::assertSame(
            '<div class="stmt-tables"><span class="chip chip-ghost">users</span> '
            . '<span class="chip chip-sm" title="The database call that was matched">pdo.query</span></div>',
            (new StatementCard())->tables($entry),
        );
    }

    public function testTablesSayNothingAboutACallNoStatementWasReadFrom(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Unknown,
            TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown())),
            [],
            [],
            new CallSite('a.php', 3, 'f', CallSite::UNMATCHED),
            [],
        );

        self::assertSame(
            '<div class="stmt-tables"><span class="chip chip-sm" title="The database call that was matched">unmatched</span></div>',
            (new StatementCard())->tables($entry),
        );
    }

    public function testCaveatsDoNotPromiseStatementsForACallNoneWereReadFrom(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Unknown,
            TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown())),
            [],
            [],
            new CallSite('a.php', 3, 'f', CallSite::UNREACHED),
            [],
        );

        self::assertSame(
            '<div class="notice notice-warn"><ul><li>The call was found but never examined,'
            . ' so nothing was read from it.</li></ul></div>',
            (new StatementCard())->caveats($entry),
        );
    }

    public function testTablesSayWhenTheStatementNamesNone(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [],
        );

        self::assertStringContainsString('no table named', (new StatementCard())->tables($entry));
    }

    public function testCaveatsWarnAboutWhatTheStatementDoesNotSay(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())),
            [],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [],
            false,
            ['App\\R::find', 'App\\R::run'],
        );
        $caveats = (new StatementCard())->caveats($entry);

        self::assertStringContainsString('may not be all of them', $caveats);
        self::assertStringContainsString('may be unreachable', $caveats);
        self::assertStringContainsString('App\\R::find → App\\R::run', $caveats);
    }

    public function testCaveatsAreSilentWhenThereIsNothingToWarnAbout(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [],
        );

        self::assertSame('', (new StatementCard())->caveats($entry));
    }

    public function testValuesListTheBindParameters(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [new Placeholder(':id', 0, 'id', new ValueDomain('string', ['a', 'b'], true, []))],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [],
        );

        self::assertStringContainsString('<code>&#039;a&#039;|&#039;b&#039;</code>', (new StatementCard())->values($entry));
    }

    public function testValuesAreSilentWhenTheStatementTakesNone(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [],
        );

        self::assertSame('', (new StatementCard())->values($entry));
    }

    public function testValueRowSaysWhenNothingWasBound(): void
    {
        self::assertSame(
            '<tr><td class="tight"><code>?</code></td><td class="tight"><code>?</code></td>'
            . '<td><span class="none">unbound</span></td></tr>',
            (new StatementCard())->valueRow(new Placeholder('?', 0, null, null)),
        );
    }

    public function testValueRowSaysWhatAnOpenValueIsKnownToBe(): void
    {
        self::assertSame(
            '<tr><td class="tight"><code>?</code></td><td class="tight"><code>string</code></td>'
            . '<td><span class="muted">not pinned down: external</span></td></tr>',
            (new StatementCard())->valueRow(new Placeholder('?', 0, null, new ValueDomain('string', [], false, ['external']))),
        );
    }

    public function testOpenValueSaysSoWhenNothingIsKnownAboutWhereItCameFrom(): void
    {
        self::assertSame('not pinned down', (new StatementCard())->openValue([]));
    }

    public function testFindingsCarryTheirSeverity(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [Finding::of(FindingRule::DynamicSql, 'spliced')],
        );

        self::assertSame(
            '<section class="stmt-block"><h4>Findings</h4><ul class="finding-list">'
            . '<li><span class="chip s-warn">medium</span><span>spliced</span></li></ul></section>',
            (new StatementCard())->findings($entry),
        );
    }

    public function testFindingsAreSilentWhenThereIsNothingToReport(): void
    {
        $entry = new CatalogEntry(
            'abc',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            [],
        );

        self::assertSame('', (new StatementCard())->findings($entry));
    }

    /**
     * @return list<array{StatementKind, string}>
     */
    public static function providerKindRole(): array
    {
        return [
            [StatementKind::Select, 'k-select'],
            [StatementKind::Insert, 'k-insert'],
            [StatementKind::Replace, 'k-insert'],
            [StatementKind::Merge, 'k-insert'],
            [StatementKind::Update, 'k-update'],
            [StatementKind::Delete, 'k-delete'],
            [StatementKind::Create, 'k-schema'],
            [StatementKind::Alter, 'k-schema'],
            [StatementKind::Drop, 'k-schema'],
            [StatementKind::Truncate, 'k-schema'],
            [StatementKind::Call, 'k-other'],
            [StatementKind::Show, 'k-other'],
            [StatementKind::Explain, 'k-other'],
            [StatementKind::Transaction, 'k-other'],
            [StatementKind::Other, 'k-other'],
            [StatementKind::Unknown, 'k-other'],
        ];
    }

    #[DataProvider('providerKindRole')]
    public function testKindRoleWritesEachKindInItsOwnHue(StatementKind $kind, string $expected): void
    {
        self::assertSame($expected, (new StatementCard())->kindRole($kind->value));
    }

    public function testKindRoleFallsBackToTheNeutralHueForAKindItDoesNotKnow(): void
    {
        self::assertSame('k-other', (new StatementCard())->kindRole('lateral-join'));
    }

    /**
     * @return list<array{Resolution, string}>
     */
    public static function providerResolutionRole(): array
    {
        return [
            [Resolution::Resolved, 's-ok'],
            [Resolution::ExternalInput, 's-danger'],
            [Resolution::IncompleteModel, 's-warn'],
            [Resolution::Incomplete, 's-warn'],
            [Resolution::NotAnalyzed, 's-neutral'],
        ];
    }

    #[DataProvider('providerResolutionRole')]
    public function testResolutionRoleWritesTheStateOfTheReading(Resolution $resolution, string $expected): void
    {
        self::assertSame($expected, (new StatementCard())->resolutionRole($resolution));
    }

    /**
     * @return list<array{Severity, string}>
     */
    public static function providerSeverityRole(): array
    {
        return [
            [Severity::High, 's-danger'],
            [Severity::Medium, 's-warn'],
            [Severity::Low, 's-neutral'],
            [Severity::Info, 's-neutral'],
        ];
    }

    #[DataProvider('providerSeverityRole')]
    public function testSeverityRoleWritesHowMuchAttentionIsWanted(Severity $severity, string $expected): void
    {
        self::assertSame($expected, (new StatementCard())->severityRole($severity));
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
            '<article class="stmt" id="a1" data-severity="high"><header class="stmt-head"><span class="chip k-select">SELECT</span><span class="chip s-da'
                . 'nger" title="The values were followed to runtime input, so the text cannot be fixed.">external-input</span><span class="chip s-danger">high<'
                . '/span><span class="stmt-site">src/a.php:4</span><span class="stmt-fn">R::find</span><a class="anchor" href="#a1" title="a1">#</a></header><p'
                . 're class="sql"><span class="tok-kw">SELECT</span> id <span class="tok-kw">FROM</span> posts <span class="tok-kw">WHERE</span> slug = <span c'
                . 'lass="hole hole-external" title="This is a gap: external input fills it. Written as $_GET[&quot;s&quot;].">{$}</span></pre><div class="stmt-'
                . 'tables"><span class="chip chip-ghost">posts</span> <span class="chip chip-sm" title="The database call that was matched">pdo.query</span></d'
                . 'iv><div class="notice notice-warn"><ul><li>Assembled from parts that vary independently, so some of these may be unreachable.</li><li>Read t'
                . 'hrough R::find → R::run.</li></ul></div><div class="stmt-blocks"><section class="stmt-block"><h4>Values</h4><div class="table-wrap"><table><'
                . 'thead><tr><th class="tight">Parameter</th><th class="tight">Type</th><th>Bound to</th></tr></thead><tbody><tr><td class="tight"><code>?</cod'
                . 'e></td><td class="tight"><code>int</code></td><td><code>7</code></td></tr></tbody></table></div></section><section class="stmt-block"><h4>Fi'
                . 'ndings</h4><ul class="finding-list"><li><span class="chip s-danger">high</span><span>spliced</span></li></ul></section></div></article>',
            (new StatementCard())->render($entries[0]),
        );
    }
}
