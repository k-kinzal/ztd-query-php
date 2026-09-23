<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Sql\StatementKind;

#[CoversClass(Palette::class)]
final class PaletteTest extends TestCase
{
    /**
     * @return list<array{StatementKind, string}>
     */
    public static function providerKind(): array
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

    #[DataProvider('providerKind')]
    public function testKindWritesEachKindInItsOwnHue(StatementKind $kind, string $expected): void
    {
        self::assertSame($expected, (new Palette())->kind($kind->value));
    }

    public function testKindGroupFallsBackForAKindItDoesNotKnow(): void
    {
        self::assertSame('other', (new Palette())->kindGroup('lateral-join'));
        self::assertSame('schema', (new Palette())->kindGroup('alter'));
    }

    /**
     * @return list<array{Resolution, string}>
     */
    public static function providerResolution(): array
    {
        return [
            [Resolution::Resolved, 's-ok'],
            [Resolution::ExternalInput, 's-danger'],
            [Resolution::IncompleteModel, 's-open'],
            [Resolution::Incomplete, 's-open'],
            [Resolution::NotAnalyzed, 's-neutral'],
        ];
    }

    #[DataProvider('providerResolution')]
    public function testResolutionWritesTheStateOfTheReading(Resolution $resolution, string $expected): void
    {
        self::assertSame($expected, (new Palette())->resolution($resolution));
    }

    /**
     * @return list<array{Severity, string}>
     */
    public static function providerSeverity(): array
    {
        return [
            [Severity::High, 's-danger'],
            [Severity::Medium, 's-warn'],
            [Severity::Low, 's-neutral'],
            [Severity::Info, 's-neutral'],
        ];
    }

    #[DataProvider('providerSeverity')]
    public function testSeverityWritesHowMuchAttentionIsWanted(Severity $severity, string $expected): void
    {
        self::assertSame($expected, (new Palette())->severity($severity));
    }

    public function testBarFollowsTheChipItGoesWith(): void
    {
        self::assertSame('bar-ok', (new Palette())->bar('s-ok'));
    }
}
