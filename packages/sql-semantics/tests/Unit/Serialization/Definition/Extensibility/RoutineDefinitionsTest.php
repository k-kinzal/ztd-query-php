<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration;
use SqlSemantics\Model\Definition\Routine\ParameterMode;
use SqlSemantics\Model\Definition\Routine\RoutineParameter;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Definition\Routine as Statement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Extensibility\RoutineDefinitions;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(RoutineDefinitions::class)]
#[Medium]
final class RoutineDefinitionsTest extends TestCase
{
    #[TestWith(["CREATE FUNCTION f() RETURNS SETOF integer LANGUAGE sql WINDOW AS 'x'", 'CREATE FUNCTION "f"() RETURNS SETOF integer LANGUAGE "sql" WINDOW AS \'x\''])]
    #[TestWith(['CREATE FUNCTION f() RETURNS TABLE (a integer) RETURN 1', 'CREATE FUNCTION "f"() RETURNS TABLE("a" integer) LANGUAGE "sql" RETURN 1'])]
    #[TestWith(['CREATE FUNCTION f(OUT a integer) BEGIN ATOMIC END', 'CREATE FUNCTION "f"(OUT "a" integer) LANGUAGE "sql" BEGIN ATOMIC END'])]
    #[TestWith(["CREATE PROCEDURE p() LANGUAGE c AS 'lib', 'sym'", 'CREATE PROCEDURE "p"() LANGUAGE "c" AS \'lib\', \'sym\''])]
    public function testWriteSpellsEachResultForm(string $sql, string $expected): void
    {
        self::assertSame($expected, RoutineDefinitions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    public function testWriteIgnoresOtherStatements(): void
    {
        self::assertNull(RoutineDefinitions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    public function testDefinitionWritesOrReplaceAndTransforms(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE OR REPLACE PROCEDURE p() LANGUAGE plperl TRANSFORM FOR TYPE hstore, FOR TYPE json AS 'x'");
        self::assertInstanceOf(Statement\CreateProcedureStatement::class, $statement);
        self::assertSame('CREATE OR REPLACE PROCEDURE "p"() LANGUAGE "plperl" TRANSFORM FOR TYPE "hstore", FOR TYPE json AS \'x\'', RoutineDefinitions::definition($statement, 'PROCEDURE', [], false)->toString());
    }

    public function testParameterWritesTheDefault(): void
    {
        $declaration = new Declaration\ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), ParameterMode::InputOutput, 'n'), Expression::literal(3, Dialect::PostgreSql));
        self::assertSame('INOUT "n" integer DEFAULT 3', RoutineDefinitions::parameter($declaration)->toString());
    }

    public function testTypeWritesAColumnTypeReference(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TEXT)')))->bind('CREATE FUNCTION f() RETURNS t.a%TYPE RETURN 1');
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        self::assertSame('"t"."a" %TYPE', RoutineDefinitions::type($statement->result->type)->toString());
    }

    public function testBodyWritesEachStep(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FUNCTION f() RETURNS integer BEGIN ATOMIC SELECT 1; RETURN 2; END');
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        self::assertSame('BEGIN ATOMIC SELECT 1; RETURN 2; END', RoutineDefinitions::body($statement->implementation->body)->toString());
    }

    public function testReturnedWritesTheValue(): void
    {
        self::assertSame('RETURN 5', RoutineDefinitions::returned(new Declaration\ReturnBody(Expression::literal(5, Dialect::PostgreSql)))->toString());
    }
}
