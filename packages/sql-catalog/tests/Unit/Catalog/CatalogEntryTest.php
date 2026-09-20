<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(CatalogEntry::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Severity::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(Origin::class)]
final class CatalogEntryTest extends TestCase
{
    public function testSqlWritesThePatternWithItsGaps(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())));
        $entry = new CatalogEntry(
            'id',
            StatementKind::Select,
            $pattern,
            [],
            [],
            new CallSite('a.php', 1, 'f', 'pdo.query'),
            [],
        );
        self::assertSame('SELECT * FROM {$}', $entry->sql());
        self::assertFalse($entry->isExact());
    }

    public function testIsExactWhenNothingIsLeftOpen(): void
    {
        $entry = new CatalogEntry(
            'id',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 1, 'f', 'pdo.query'),
            [],
        );
        self::assertTrue($entry->isExact());
    }

    public function testSeverityIsTheHighestOfTheFindings(): void
    {
        $entry = new CatalogEntry(
            'id',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 1, 'f', 'pdo.query'),
            [Finding::of(FindingRule::UnresolvedSql, 'a'), Finding::of(FindingRule::ExternalInput, 'b')],
        );
        self::assertSame(Severity::High, $entry->severity());
    }

    public function testSeverityIsInformationalWithoutFindings(): void
    {
        $entry = new CatalogEntry(
            'id',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 1, 'f', 'pdo.query'),
            [],
        );
        self::assertSame(Severity::Info, $entry->severity());
    }

    public function testResolutionReadsHowFarTheAnalyzerGot(): void
    {
        $resolved = new CatalogEntry('id', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 's'), []);
        $open = new CatalogEntry(
            'id',
            StatementKind::Select,
            TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())),
            [],
            [],
            new CallSite('a.php', 1, 'f', 's'),
            [],
        );

        self::assertSame(Resolution::Resolved, $resolved->resolution());
        self::assertSame(Resolution::ExternalInput, $open->resolution());
    }

    public function testTheAlternativesAreMarkedAsReachableOrNot(): void
    {
        $entry = new CatalogEntry('id', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 's'), [], false, ['App\\R::find']);

        self::assertFalse($entry->correlated);
        self::assertSame(['App\\R::find'], $entry->through);
    }

    public function testHasFindingLooksForOneRule(): void
    {
        $entry = new CatalogEntry(
            'id',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 1, 'f', 'pdo.query'),
            [Finding::of(FindingRule::DynamicSql, 'a')],
        );
        self::assertTrue($entry->hasFinding(FindingRule::DynamicSql));
        self::assertFalse($entry->hasFinding(FindingRule::ExternalInput));
    }

    public function testPartsAreTheRunsAndGapsTheStatementIsKnownIn(): void
    {
        $entry = new CatalogEntry(
            'id',
            StatementKind::Select,
            TextPattern::fromSegments([
                new LiteralText('SELECT * FROM t WHERE id = '),
                new TextHole(Origin::External, TypeShape::unknown()),
            ]),
            ['t'],
            [],
            new CallSite('a.php', 1, 'f', 'pdo.query'),
            [],
        );

        self::assertSame(
            [false, true],
            array_map(static fn (StatementPart $part): bool => $part->isGap, $entry->parts()),
        );
    }

    public function testFirstGapIsTheFirstOneOrNothingWhenThereIsNone(): void
    {
        $open = new CatalogEntry(
            'id',
            StatementKind::Select,
            TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')),
            [],
            [],
            new CallSite('a.php', 1, 'f', CallSite::UNREACHED),
            [],
        );
        $closed = new CatalogEntry(
            'id',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            [],
            [],
            new CallSite('a.php', 1, 'f', 'pdo.query'),
            [],
        );

        self::assertSame('$db->query($sql)', $open->firstGap()?->expression);
        self::assertNull($closed->firstGap());
    }
}
