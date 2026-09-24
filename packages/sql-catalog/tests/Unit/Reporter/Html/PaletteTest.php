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
            [StatementKind::Select, 'tone-blue'],
            [StatementKind::Insert, 'tone-teal'],
            [StatementKind::Replace, 'tone-teal'],
            [StatementKind::Merge, 'tone-teal'],
            [StatementKind::Update, 'tone-violet'],
            [StatementKind::Delete, 'tone-pink'],
            [StatementKind::Create, 'tone-indigo'],
            [StatementKind::Alter, 'tone-indigo'],
            [StatementKind::Drop, 'tone-indigo'],
            [StatementKind::Truncate, 'tone-indigo'],
            [StatementKind::Call, 'tone-slate'],
            [StatementKind::Show, 'tone-slate'],
            [StatementKind::Explain, 'tone-slate'],
            [StatementKind::Transaction, 'tone-slate'],
            [StatementKind::Other, 'tone-slate'],
            [StatementKind::Unknown, 'tone-slate'],
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
            [Resolution::Resolved, 'tone-ok'],
            [Resolution::ExternalInput, 'tone-danger'],
            [Resolution::IncompleteModel, 'chip-ghost'],
            [Resolution::Incomplete, 'chip-ghost'],
            [Resolution::NotAnalyzed, 'tone-neutral'],
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
            [Severity::High, 'tone-danger'],
            [Severity::Medium, 'tone-warn'],
            [Severity::Low, 'tone-neutral'],
            [Severity::Info, 'tone-neutral'],
        ];
    }

    #[DataProvider('providerSeverity')]
    public function testSeverityWritesHowMuchAttentionIsWanted(Severity $severity, string $expected): void
    {
        self::assertSame($expected, (new Palette())->severity($severity));
    }

    public function testBarKeepsAToneAndHatchesAnOpenSearch(): void
    {
        self::assertSame('tone-ok', (new Palette())->bar('tone-ok'));
        self::assertSame('is-open', (new Palette())->bar(Palette::OPEN));
    }
}
