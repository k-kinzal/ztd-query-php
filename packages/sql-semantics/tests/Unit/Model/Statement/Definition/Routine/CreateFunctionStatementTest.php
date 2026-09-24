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
use SqlSemantics\Model\Statement\Definition\Routine\CreateFunctionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateFunctionStatement::class)]
#[Medium]
final class CreateFunctionStatementTest extends TestCase
{
    public function testBindsTheDefinitionAndWritesItBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE OR REPLACE FUNCTION app.f(a integer, b integer DEFAULT -1) RETURNS SETOF integer LANGUAGE sql STABLE ROWS 5 SET search_path = app AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame([true, ['app', 'f'], true, 'sql'], [$statement->orReplace, $statement->name->parts, $statement->result->setOf, $statement->implementation->language]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\UnaryExpression::class, $statement->parameters[1]->default);
        self::assertSame('CREATE OR REPLACE FUNCTION "app"."f"("a" integer, "b" integer DEFAULT (- 1)) RETURNS SETOF integer LANGUAGE "sql" STABLE ROWS 5 SET "search_path" = "app" AS \'SELECT a\'', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithResultReplacesTheResultType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS integer LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertStringContainsString('RETURNS SETOF text', $statement->withResult(new Declaration\RoutineResult(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), true))->toString());
        self::assertFalse($statement->result->setOf);
    }

    public function testWithWindowReplacesTheWindowFlag(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS integer LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertStringContainsString(' WINDOW ', $statement->withWindow(true)->toString());
        self::assertFalse($statement->window);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS integer LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE FUNCTION "f"("a" integer) RETURNS integer LANGUAGE "sql" AS \'SELECT a\'', $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS integer LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithOrReplaceReplacesTheReplacementPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS integer LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertStringStartsWith('CREATE OR REPLACE ', $statement->withOrReplace(true)->toString());
        self::assertFalse($statement->orReplace);
    }

    public function testWithNameReplacesTheRoutine(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS integer LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertStringContainsString('FUNCTION "app"."g"', $statement->withName(new QualifiedName(['app', 'g']))->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c', 'd']));
    }

    public function testWithParametersReplacesTheDeclarations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS integer LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        $parameters = [...$statement->parameters, new Declaration\ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), ParameterMode::Input, 'extra'), Expression::literal(7, Dialect::PostgreSql))];
        self::assertStringContainsString('"extra" integer DEFAULT 7', $statement->withParameters($parameters)->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withParameters([...$parameters, new Declaration\ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), ParameterMode::Input, 'late'))]);
    }

    public function testWithImplementationReplacesTheBody(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS integer LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        $text = Expression::literal('BEGIN END', Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $text);
        self::assertStringEndsWith("LANGUAGE \"plpgsql\" AS 'BEGIN END'", $statement->withImplementation(new Declaration\RoutineImplementation('plpgsql', [], new Declaration\DefinitionBody($text)))->toString());
    }

    public function testWithOptionsReplacesTheAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(a integer) RETURNS integer LANGUAGE sql AS 'SELECT a'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertStringContainsString('IMMUTABLE SUPPORT "s"', $statement->withOptions([Option\Volatility::Immutable, new Option\SupportFunction(new QualifiedName(['s']))])->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([Option\Volatility::Immutable, Option\Volatility::Stable]);
    }
}
