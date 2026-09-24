<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Extensibility\RoutineOptions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Option;
use SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineOptions::class)]
#[Medium]
final class RoutineOptionsTest extends TestCase
{
    public function testReadKeepsTheRequestOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() NOT LEAKPROOF IMMUTABLE CALLED ON NULL INPUT');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertSame([Option\LeakproofBehavior::NotLeakproof, Option\Volatility::Immutable, Option\NullInputBehavior::Called], $statement->changes);
    }

    #[TestWith(['STRICT', Option\NullInputBehavior::Strict])]
    #[TestWith(['RETURNS NULL ON NULL INPUT', Option\NullInputBehavior::Strict])]
    #[TestWith(['EXTERNAL SECURITY INVOKER', RoutineSecurity::Invoker])]
    #[TestWith(['SECURITY DEFINER', RoutineSecurity::Definer])]
    #[TestWith(['STABLE', Option\Volatility::Stable])]
    #[TestWith(['LEAKPROOF', Option\LeakproofBehavior::Leakproof])]
    public function testOptionNormalizesSynonyms(string $attribute, Option\RoutineOption|RoutineSecurity $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() ' . $attribute);
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertSame($expected, $statement->changes[0]);
    }

    #[TestWith(['safe', Option\ParallelSafety::Safe])]
    #[TestWith(['UNSAFE', Option\ParallelSafety::Unsafe])]
    public function testParallelReadsTheSafety(string $value, Option\ParallelSafety $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() PARALLEL ' . $value);
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertSame($expected, $statement->changes[0]);
    }

    #[TestWith(['PARALLEL "SAFE"'])]
    #[TestWith(['PARALLEL maybe'])]
    #[TestWith(['COST 0'])]
    #[TestWith(['ROWS -5'])]
    #[TestWith(['COST 1e999'])]
    #[TestWith(['SUPPORT a.b.c.d'])]
    public function testOptionRejectsImpossibleValues(string $attribute): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() ' . $attribute);
            self::fail('The attribute must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertContains($error->violation, [InputViolation::RoutineAttribute, InputViolation::CatalogObjectName]);
        }
    }

    public function testSettingReadsSetAndReset(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() SET search_path TO DEFAULT RESET ALL');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertInstanceOf(Option\RoutineSetting::class, $statement->changes[0]);
        self::assertEquals(new Option\RoutineReset(null), $statement->changes[1]);
    }

    public function testEstimateDropsThePlusSignAndSeparators(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() COST +1_000.5');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        $cost = $statement->changes[0];
        self::assertInstanceOf(Option\ExecutionCost::class, $cost);
        self::assertSame('1000.5', $cost->cost->text);
    }
}
