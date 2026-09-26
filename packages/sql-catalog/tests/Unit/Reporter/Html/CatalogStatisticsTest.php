<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Catalog\Finding;
use SqlCatalog\Core\Catalog\FindingRule;
use SqlCatalog\Core\Catalog\Resolution;
use SqlCatalog\Core\Catalog\Severity;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Reporter\Html\CatalogStatistics;

#[CoversClass(CatalogStatistics::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Severity::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(\SqlCatalog\Core\Text\LiteralText::class)]
final class CatalogStatisticsTest extends TestCase
{
    public function testStatementsCountsWhatTheCatalogHolds(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['t'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(1, (new CatalogStatistics($catalog))->statements());
    }

    public function testResolvedCountsTheStatementsWithNoGaps(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(1, (new CatalogStatistics($catalog))->resolved());
    }

    public function testOpenCountsTheStatementsWhoseSearchDidNotClose(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(1, (new CatalogStatistics($catalog))->open());
    }

    public function testFindingsCountsEveryFindingOfEveryStatement(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'one'),
                Finding::of(FindingRule::ExternalInput, 'two'),
            ]),
        ]);

        self::assertSame(2, (new CatalogStatistics($catalog))->findings());
    }

    public function testAtLeastCountsTheStatementsWorthLookingAt(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'one'),
            ]),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), [
                Finding::of(FindingRule::AnalysisIncomplete, 'two'),
            ]),
        ]);

        self::assertSame(1, (new CatalogStatistics($catalog))->atLeast(Severity::High));
    }

    public function testByResolutionAccountsForEveryStatementUnderEveryResolution(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            ['resolved' => 1, 'external-input' => 0, 'incomplete-model' => 0, 'incomplete' => 0, 'not-analyzed' => 0],
            (new CatalogStatistics($catalog))->byResolution(),
        );
    }

    public function testByKindCountsTheKindsMostFirst(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Insert, TextPattern::fromText('INSERT INTO t VALUES (1)'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
            new CatalogEntry('c', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 3, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['select' => 2, 'insert' => 1], (new CatalogStatistics($catalog))->byKind());
    }

    public function testByRuleCountsTheFindingsMostFirst(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'one'),
                Finding::of(FindingRule::ExternalInput, 'two'),
            ]),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'three'),
            ]),
        ]);

        self::assertSame(['dynamic-sql' => 2, 'external-input' => 1], (new CatalogStatistics($catalog))->byRule());
    }

    public function testTablesSayHowEachOneIsUsed(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Insert, TextPattern::fromText('INSERT'), ['posts'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
            new CatalogEntry('c', StatementKind::Select, TextPattern::fromText('SELECT 2'), ['users'], [], new CallSite('a.php', 3, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            [
                ['name' => 'posts', 'reads' => 1, 'writes' => 1, 'statements' => 2],
                ['name' => 'users', 'reads' => 1, 'writes' => 0, 'statements' => 1],
            ],
            (new CatalogStatistics($catalog))->tables(),
        );
    }

    public function testFilesSayWhatWasFoundInEachOfThem(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::AnalysisIncomplete, 'one'),
            ]),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            [
                'a.php' => ['statements' => 1, 'open' => 0, 'findings' => 0],
                'b.php' => ['statements' => 1, 'open' => 1, 'findings' => 1],
            ],
            (new CatalogStatistics($catalog))->files(),
        );
    }

    public function testFlaggedListsTheReportedStatementsWorstFirst(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('low', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::AnalysisIncomplete, 'one'),
            ]),
            new CatalogEntry('high', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'two'),
            ]),
            new CatalogEntry('none', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('a.php', 3, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['high', 'low'], array_column((new CatalogStatistics($catalog))->flagged(), 'id'));
    }
}
