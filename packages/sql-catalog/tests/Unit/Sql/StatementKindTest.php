<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Sql\StatementKind;

#[CoversClass(StatementKind::class)]
final class StatementKindTest extends TestCase
{
    public function testEveryKindIsCoveredByBothQuestions(): void
    {
        self::assertCount(count(StatementKind::cases()), array_unique(array_merge(
            array_map(static fn (array $case): string => $case[0]->value, self::providerIsWrite()),
        )));
        self::assertCount(count(StatementKind::cases()), array_unique(array_merge(
            array_map(static fn (array $case): string => $case[0]->value, self::providerIsSchema()),
        )));
    }

    #[DataProvider('providerIsWrite')]
    public function testIsWrite(StatementKind $kind, bool $expected): void
    {
        self::assertSame($expected, $kind->isWrite());
    }

    /**
     * @return list<array{StatementKind, bool}>
     */
    public static function providerIsWrite(): array
    {
        return [
            [StatementKind::Insert, true],
            [StatementKind::Update, true],
            [StatementKind::Delete, true],
            [StatementKind::Replace, true],
            [StatementKind::Merge, true],
            [StatementKind::Truncate, true],
            [StatementKind::Select, false],
            [StatementKind::Create, false],
            [StatementKind::Alter, false],
            [StatementKind::Drop, false],
            [StatementKind::Call, false],
            [StatementKind::Show, false],
            [StatementKind::Explain, false],
            [StatementKind::Transaction, false],
            [StatementKind::Other, false],
            [StatementKind::Unknown, false],
        ];
    }

    #[DataProvider('providerIsSchema')]
    public function testIsSchema(StatementKind $kind, bool $expected): void
    {
        self::assertSame($expected, $kind->isSchema());
    }

    /**
     * @return list<array{StatementKind, bool}>
     */
    public static function providerIsSchema(): array
    {
        return [
            [StatementKind::Create, true],
            [StatementKind::Alter, true],
            [StatementKind::Drop, true],
            [StatementKind::Truncate, true],
            [StatementKind::Select, false],
            [StatementKind::Insert, false],
            [StatementKind::Update, false],
            [StatementKind::Delete, false],
            [StatementKind::Replace, false],
            [StatementKind::Merge, false],
            [StatementKind::Call, false],
            [StatementKind::Show, false],
            [StatementKind::Explain, false],
            [StatementKind::Transaction, false],
            [StatementKind::Other, false],
            [StatementKind::Unknown, false],
        ];
    }
}
