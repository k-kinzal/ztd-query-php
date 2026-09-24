<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Option;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Option\OptionInvariant::class)]
#[Medium]
final class OptionInvariantTest extends TestCase
{
    #[TestWith(['1e3'])]
    #[TestWith(['0.25'])]
    public function testEstimateAcceptsPositiveNumbers(string $value): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() COST ' . $value);
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        $change = $statement->changes[0];
        self::assertInstanceOf(Option\ExecutionCost::class, $change);
        Option\OptionInvariant::estimate($change->cost);
        self::assertSame($value, $change->cost->text);
    }

    public function testEstimateRejectsAnInfiniteNumber(): void
    {
        $value = Expression::literal('1e999', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        Option\OptionInvariant::estimate($value);
    }

    public function testOptionsAllowsRepeatedSettings(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURE p SET a = 1 SET b = 2 RESET a SECURITY INVOKER');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        Option\OptionInvariant::options($statement->changes, true);
        self::assertCount(4, $statement->changes);
    }

    public function testOptionsRejectsARepeatedAttribute(): void
    {
        $this->expectException(InvalidStructure::class);
        Option\OptionInvariant::options([Option\Volatility::Stable, Option\Volatility::Immutable], false);
    }

    public function testOptionsRejectsAFunctionAttributeOfAProcedure(): void
    {
        Option\OptionInvariant::options([RoutineSecurity::Invoker], true);
        $this->expectException(InvalidStructure::class);
        Option\OptionInvariant::options([Option\ParallelSafety::Safe], true);
    }

    public function testRowsRequiresASetResult(): void
    {
        $rows = Expression::literal(5, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $rows);
        Option\OptionInvariant::rows([new Option\ResultRows($rows)], true);
        $this->expectException(InvalidStructure::class);
        Option\OptionInvariant::rows([new Option\ResultRows($rows)], false);
    }
}
