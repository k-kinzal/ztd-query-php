<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration;
use SqlSemantics\Model\Definition\Routine\Option;
use SqlSemantics\Model\Definition\Routine\ParameterMode;
use SqlSemantics\Model\Definition\Routine\RoutineParameter;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Routine\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateProcedureStatement::class)]
#[Medium]
final class CreateProcedureStatementTest extends TestCase
{
    public function testBindsTheProcedureAndWritesItBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)'));
        $statement = $binder->bind('CREATE OR REPLACE PROCEDURE p(v integer) EXTERNAL SECURITY INVOKER BEGIN ATOMIC INSERT INTO t VALUES (v); DELETE FROM t WHERE a = p.v; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertSame('CREATE OR REPLACE PROCEDURE "p"("v" integer) LANGUAGE "sql" SECURITY INVOKER BEGIN ATOMIC INSERT INTO "public"."t" VALUES ("v"); DELETE FROM "public"."t" WHERE ("a" = "p"."v"); END', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE PROCEDURE f(a integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE PROCEDURE "f"("a" integer) LANGUAGE "sql" AS \'SELECT a\'', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE PROCEDURE f(a integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithOrReplaceReplacesTheReplacementPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE PROCEDURE f(a integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertStringStartsWith('CREATE OR REPLACE ', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrReplace(true)));
        self::assertFalse($statement->orReplace);
    }

    public function testWithNameReplacesTheRoutine(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE PROCEDURE f(a integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertStringContainsString('PROCEDURE "app"."g"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['app', 'g']))));
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c', 'd']));
    }

    public function testWithParametersReplacesTheDeclarations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE PROCEDURE f(a integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $parameters = [...$statement->parameters, new Declaration\ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), ParameterMode::Input, 'extra'), Expression::literal(7, Dialect::PostgreSql))];
        self::assertStringContainsString('"extra" integer DEFAULT 7', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withParameters($parameters)));
        $this->expectException(InvalidStructure::class);
        $statement->withParameters([...$parameters, new Declaration\ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), ParameterMode::Input, 'late'))]);
    }

    public function testWithImplementationReplacesTheBody(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE PROCEDURE f(a integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $text = Expression::literal('BEGIN END', Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $text);
        self::assertStringEndsWith("LANGUAGE \"plpgsql\" AS 'BEGIN END'", $statement->withImplementation(new Declaration\RoutineImplementation('plpgsql', [], new Declaration\DefinitionBody($text)))->toString());
    }

    public function testWithOptionsReplacesTheAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE PROCEDURE f(a integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertStringContainsString('SECURITY DEFINER', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOptions([\SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity::Definer])));
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([Option\Volatility::Stable]);
    }
}
