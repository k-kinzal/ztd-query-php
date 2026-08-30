<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertColumn;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertComparison;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertNumber;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertOperator;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertTruth;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;
use ZtdQuery\Shadow\Mutation\UpsertMutationRow;
use ZtdQuery\Shadow\Mutation\UpsertUpdate;

#[CoversClass(UpsertUpdate::class)]
#[UsesClass(UpsertMutationRow::class)]
#[UsesClass(UpsertExpression::class)]
#[UsesClass(UpsertColumn::class)]
#[UsesClass(UpsertOperator::class)]
#[UsesClass(UpsertTruth::class)]
#[UsesClass(UnsupportedSqlException::class)]
#[UsesClass(UpsertComparison::class)]
#[UsesClass(UpsertNumber::class)]
final class UpsertUpdateTest extends TestCase
{
    public function testIncomingRowLeavesARowAloneWhereTheDatabaseWorkedNothingOut(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], [], null, null, false);

        self::assertSame(['id' => 1, 'a' => 2], $update->incomingRow(['id' => 1, 'a' => 2]));
    }

    public function testIncomingRowTakesOffWhatTheDatabaseWorkedOut(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], [], null, null, true);
        $codec = new UpsertMutationRow();

        $row = ['id' => 1, $codec->valueColumn(0) => 9, $codec->predicateColumn() => 1];

        self::assertSame(['id' => 1], $update->incomingRow($row));
    }

    public function testAppliesToEveryConflictWhereTheStatementWroteNoCondition(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], [], null, null, false);

        self::assertTrue($update->applies([], ['id' => 1], ['id' => 1], false));
    }

    public function testAppliesReadsTheConditionHereWhereTheDatabaseWorkedNothingOut(): void
    {
        $never = UpsertExpression::binary(
            UpsertExpressionKind::Equal,
            UpsertExpression::literal(1),
            UpsertExpression::literal(2),
        );
        $update = new UpsertUpdate('t', ['id'], ['a'], [], [], null, $never, false);

        self::assertFalse($update->applies([], ['id' => 1], ['id' => 1], false));
    }

    public function testAppliesTakesTheVerdictTheDatabaseWorkedOut(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], [], 'a > 0', null, true);
        $codec = new UpsertMutationRow();

        self::assertSame(
            [true, false],
            [
                $update->applies([$codec->predicateColumn() => 1], ['id' => 1], ['id' => 1], false),
                $update->applies([$codec->predicateColumn() => 0], ['id' => 1], ['id' => 1], false),
            ],
        );
    }

    public function testAppliesRefusesAConditionTheDatabaseCouldNotWorkOutForARowAlreadyChanged(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], [], 'a > 0', null, true);

        $this->expectException(UnsupportedSqlException::class);

        $update->applies([], ['id' => 1], ['id' => 1], true);
    }

    public function testAppliesRefusesAConditionTheDatabaseDidNotWorkOutAtAll(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], [], 'a > 0', null, true);

        $this->expectException(UnsupportedSqlException::class);

        $update->applies([], ['id' => 1], ['id' => 1], false);
    }

    public function testOfCarriesEverythingButTheConflictColumnsWhereNoColumnIsNamed(): void
    {
        $update = new UpsertUpdate('t', ['id'], [], [], [], null, null, false);

        self::assertSame(
            ['id' => 1, 'a' => 2],
            $update->of([], ['id' => 1, 'a' => 0], ['id' => 9, 'a' => 2], false),
        );
    }

    public function testOfAssignsEachColumnTheStatementNames(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], [], null, null, false);

        self::assertSame(['id' => 1, 'a' => 2], $update->of([], ['id' => 1, 'a' => 0], ['id' => 1, 'a' => 2], false));
    }

    public function testWithColumnReadsTheExpressionAgainstTheRowAsTheStatementLeftIt(): void
    {
        $doubled = UpsertExpression::binary(
            UpsertExpressionKind::Add,
            UpsertExpression::column(UpsertColumnSource::Existing, 'a'),
            UpsertExpression::literal(1),
        );
        $update = new UpsertUpdate('t', ['id'], ['a'], ['a' => $doubled], [], null, null, false);

        self::assertSame(['a' => 3], $update->withColumn(['a' => 2], 'a', 0, [], [], false));
    }

    public function testWithColumnTakesWhatTheDatabaseWorkedOut(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], ['a' => 'a + 1'], null, null, true);
        $codec = new UpsertMutationRow();

        self::assertSame(
            ['a' => 7],
            $update->withColumn(['a' => 2], 'a', 0, [$codec->valueColumn(0) => 7], [], false),
        );
    }

    public function testWithColumnRefusesAnAssignmentTheDatabaseCouldNotWorkOut(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], ['a' => 'a + 1'], null, null, true);

        $this->expectException(UnsupportedSqlException::class);

        $update->withColumn(['a' => 2], 'a', 0, [], [], true);
    }

    public function testPredicateReadRefusesAConditionZtdCouldNotRead(): void
    {
        $update = new UpsertUpdate('t', ['id'], ['a'], [], [], 'a > 0', null, true);

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('why');

        $update->predicateRead('why');
    }

    public function testValueReadAnswersTheAssignmentZtdRead(): void
    {
        $one = UpsertExpression::literal(1);
        $update = new UpsertUpdate('t', ['id'], ['a'], ['a' => $one], [], null, null, true);

        self::assertSame($one, $update->valueRead('a', 'why'));
    }
}
