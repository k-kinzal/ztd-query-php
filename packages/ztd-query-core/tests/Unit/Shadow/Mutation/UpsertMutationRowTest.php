<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\UpsertMutationRow;

#[CoversClass(UpsertMutationRow::class)]
final class UpsertMutationRowTest extends TestCase
{
    public function testPredicateColumnAndValueColumnNameWhatTheStatementCarriesAlongsideARow(): void
    {
        $codec = new UpsertMutationRow();
        $row = [
            'id' => 1,
            'name' => 'incoming',
            $codec->valueColumn(-1) => 'before',
            $codec->valueColumn(0) => 'evaluated',
            $codec->valueColumn(1) => 'after',
            $codec->predicateColumn() => 1,
        ];

        self::assertSame(
            ['id' => 1, 'name' => 'incoming', '__ztd_upsert_value_-1' => 'before', '__ztd_upsert_value_1' => 'after'],
            $codec->incomingRow($row, 1),
        );
        self::assertSame('__ztd_upsert_value_2', $codec->valueColumn(2));
        self::assertSame('__ztd_upsert_predicate', $codec->predicateColumn());
    }

    #[DataProvider('providerPredicateValues')]
    public function testPredicateMatchesNormalizesDatabasePredicateValues(
        bool|float|int|string|null $value,
        bool $expected,
    ): void {
        self::assertSame($expected, (new UpsertMutationRow())->predicateMatches($value));
    }

    /**
     * @return iterable<string, array{bool|float|int|string|null, bool}>
     */
    public static function providerPredicateValues(): iterable
    {
        yield 'boolean true' => [true, true];
        yield 'integer true' => [1, true];
        yield 'numeric string true' => ['1', true];
        yield 'PostgreSQL true' => ['t', true];
        yield 'boolean false' => [false, false];
        yield 'integer false' => [0, false];
        yield 'null' => [null, false];
    }
    public function testValueColumnNamesOneAssignmentSoNoTableCouldUseTheName(): void
    {
        self::assertSame('__ztd_upsert_value_0', (new UpsertMutationRow())->valueColumn(0));
    }

    public function testIncomingRowLeavesOnlyWhatTheStatementActuallyWrote(): void
    {
        $row = [
            'id' => 1,
            '__ztd_upsert_value_0' => 'a',
            '__ztd_upsert_predicate' => 1,
        ];

        self::assertSame(['id' => 1], (new UpsertMutationRow())->incomingRow($row, 1));
    }

    public function testIncomingRowLeavesACarriedValueTheClauseNeverDeclared(): void
    {
        $row = ['id' => 1, '__ztd_upsert_value_1' => 'a'];

        self::assertSame($row, (new UpsertMutationRow())->incomingRow($row, 1));
    }

}
