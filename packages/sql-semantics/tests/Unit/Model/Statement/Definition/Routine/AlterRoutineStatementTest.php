<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\Kind\RoutineKind;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Option;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterRoutineStatement::class)]
#[Medium]
final class AlterRoutineStatementTest extends TestCase
{
    public function testBindsTheChangesAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER ROUTINE app.f(integer, text) RETURNS NULL ON NULL INPUT EXTERNAL SECURITY DEFINER SET TIME ZONE LOCAL RESTRICT');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertSame([RoutineKind::Routine, Option\NullInputBehavior::Strict, RoutineSecurity::Definer], [$statement->routine, $statement->changes[0], $statement->changes[1]]);
        self::assertSame('ALTER ROUTINE "app"."f"(integer, text) STRICT SECURITY DEFINER SET "timezone" = DEFAULT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f VOLATILE');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('ALTER FUNCTION "f" VOLATILE', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f VOLATILE');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithRoutineAppliesTheProcedureRules(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f SECURITY INVOKER');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertSame('ALTER PROCEDURE "f" SECURITY INVOKER', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withRoutine(RoutineKind::Procedure)));
        $this->expectException(InvalidStructure::class);
        $statement->withChanges([Option\Volatility::Stable])->withRoutine(RoutineKind::Procedure);
    }

    public function testWithTargetReplacesTheRoutine(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() VOLATILE');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertSame('ALTER FUNCTION "app"."g" VOLATILE', $statement->withTarget(new RoutineByName(new QualifiedName(['app', 'g'])))->toString());
        self::assertSame(['f'], $statement->target->name->parts);
    }

    public function testWithChangesRejectsARepeatedAttribute(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f VOLATILE');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertSame('ALTER FUNCTION "f" LEAKPROOF', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withChanges([Option\LeakproofBehavior::Leakproof])));
        $this->expectException(InvalidStructure::class);
        $statement->withChanges([Option\ParallelSafety::Safe, Option\ParallelSafety::Unsafe]);
    }
}
