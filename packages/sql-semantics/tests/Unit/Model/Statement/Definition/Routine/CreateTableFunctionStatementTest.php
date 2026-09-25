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
use SqlSemantics\Model\Statement\Definition\Routine\CreateTableFunctionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateTableFunctionStatement::class)]
#[Medium]
final class CreateTableFunctionStatementTest extends TestCase
{
    public function testBindsTheColumnsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE FUNCTION f(n integer) RETURNS TABLE(k integer, v text) LANGUAGE sql ROWS 3 AS 'SELECT 1, 2'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        self::assertSame(['k', 'v'], array_map(static fn (Declaration\ResultColumn $column): string => $column->name, $statement->columns));
        self::assertSame('CREATE FUNCTION "f"("n" integer) RETURNS TABLE("k" integer, "v" text) LANGUAGE "sql" ROWS 3 AS \'SELECT 1, 2\'', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithColumnsReplacesTheColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        self::assertStringContainsString('RETURNS TABLE("x" text)', $statement->withColumns([new Declaration\ResultColumn('x', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'))])->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withColumns([new Declaration\ResultColumn('x', TypeDescriptor::builtin(Dialect::PostgreSql, 'text')), new Declaration\ResultColumn('x', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'))]);
    }

    public function testWithWindowReplacesTheWindowFlag(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        self::assertStringContainsString(' WINDOW ', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withWindow(true)));
        self::assertFalse($statement->window);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE FUNCTION "f"("a" integer) RETURNS TABLE("k" integer) LANGUAGE "sql" AS \'SELECT a\'', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithOrReplaceReplacesTheReplacementPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        self::assertStringStartsWith('CREATE OR REPLACE ', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrReplace(true)));
        self::assertFalse($statement->orReplace);
    }

    public function testWithNameReplacesTheRoutine(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        self::assertStringContainsString('FUNCTION "app"."g"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['app', 'g']))));
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c', 'd']));
    }

    public function testWithParametersReplacesTheDeclarations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        $parameters = [...$statement->parameters, new Declaration\ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), ParameterMode::Input, 'extra'), Expression::literal(7, Dialect::PostgreSql))];
        self::assertStringContainsString('"extra" integer DEFAULT 7', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withParameters($parameters)));
        $this->expectException(InvalidStructure::class);
        $statement->withParameters([...$parameters, new Declaration\ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), ParameterMode::Input, 'late'))]);
    }

    public function testWithImplementationReplacesTheBody(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        $text = Expression::literal('BEGIN END', Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $text);
        self::assertStringEndsWith("LANGUAGE \"plpgsql\" AS 'BEGIN END'", $statement->withImplementation(new Declaration\RoutineImplementation('plpgsql', [], new Declaration\DefinitionBody($text)))->toString());
    }

    public function testWithOptionsReplacesTheAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateTableFunctionStatement::class, $statement);
        self::assertStringContainsString('NOT LEAKPROOF', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOptions([Option\LeakproofBehavior::NotLeakproof])));
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([Option\LeakproofBehavior::NotLeakproof, Option\LeakproofBehavior::Leakproof]);
    }
}
