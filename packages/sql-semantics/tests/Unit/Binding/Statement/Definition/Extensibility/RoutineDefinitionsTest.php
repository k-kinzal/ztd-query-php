<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Extensibility\RoutineDefinitions;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Statement\Definition\Routine as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineDefinitions::class)]
#[Medium]
final class RoutineDefinitionsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(["CREATE FUNCTION f() RETURNS integer LANGUAGE sql AS 'SELECT 1'", Statement\CreateFunctionStatement::class])]
    #[TestWith(["CREATE FUNCTION f() RETURNS TABLE (a integer) LANGUAGE sql AS 'SELECT 1'", Statement\CreateTableFunctionStatement::class])]
    #[TestWith(["CREATE FUNCTION f(OUT a integer) LANGUAGE sql AS 'SELECT 1'", Statement\CreateOutputFunctionStatement::class])]
    #[TestWith(["CREATE PROCEDURE p() LANGUAGE sql AS 'SELECT 1'", Statement\CreateProcedureStatement::class])]
    public function testCreateSelectsTheFormByItsResult(string $sql, string $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(["CREATE FUNCTION f(a integer) LANGUAGE sql AS 'SELECT 1'", InputViolation::RoutineDefinition])]
    #[TestWith(["CREATE PROCEDURE p() LANGUAGE sql WINDOW AS 'SELECT 1'", InputViolation::RoutineAttribute])]
    #[TestWith(["CREATE FUNCTION f() RETURNS integer LANGUAGE sql ROWS 5 AS 'SELECT 1'", InputViolation::RoutineAttribute])]
    #[TestWith(["CREATE FUNCTION f() RETURNS integer LANGUAGE sql LANGUAGE sql AS 'SELECT 1'", InputViolation::RoutineAttribute])]
    #[TestWith(["CREATE FUNCTION a.b.c.d() RETURNS integer LANGUAGE sql AS 'SELECT 1'", InputViolation::CatalogObjectName])]
    public function testCreateRejectsImpossibleDefinitions(string $sql, InputViolation $violation): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
            self::fail('The definition must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame($violation, $error->violation);
        }
    }

    #[TestWith(["CREATE FUNCTION f(a integer DEFAULT 1, b integer) RETURNS integer LANGUAGE sql AS 'SELECT 1'"])]
    #[TestWith(["CREATE FUNCTION f(a integer DEFAULT b) RETURNS integer LANGUAGE sql AS 'SELECT 1'"])]
    #[TestWith(["CREATE FUNCTION f(a integer DEFAULT (SELECT 1)) RETURNS integer LANGUAGE sql AS 'SELECT 1'"])]
    #[TestWith(["CREATE FUNCTION f(OUT a integer DEFAULT 1) RETURNS integer LANGUAGE sql AS 'SELECT 1'"])]
    #[TestWith(["CREATE FUNCTION f(a SETOF integer) RETURNS integer LANGUAGE sql AS 'SELECT 1'"])]
    #[TestWith(["CREATE FUNCTION f(a integer, a integer) RETURNS integer LANGUAGE sql AS 'SELECT 1'"])]
    public function testParametersRejectsImpossibleDeclarations(string $sql): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
            self::fail('The parameters must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::RoutineParameter, $error->violation);
        }
    }

    public function testResultResolvesAColumnType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TEXT)')))->bind("CREATE FUNCTION f() RETURNS SETOF t.a%TYPE LANGUAGE sql AS 'SELECT 1'");
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(ColumnTypeReference::class, $statement->result->type);
        self::assertSame('text', $statement->result->type->binding?->column->type->name);
        self::assertTrue($statement->result->setOf);
    }

    #[TestWith(["CREATE FUNCTION f() RETURNS TABLE (a SETOF integer) LANGUAGE sql AS 'SELECT 1'"])]
    #[TestWith(["CREATE FUNCTION f() RETURNS TABLE (a integer, a text) LANGUAGE sql AS 'SELECT 1'"])]
    #[TestWith(["CREATE FUNCTION f(INOUT b integer) RETURNS TABLE (a integer) LANGUAGE sql AS 'SELECT 1'"])]
    public function testColumnsRejectsImpossibleColumns(string $sql): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
            self::fail('The columns must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::RoutineParameter, $error->violation);
        }
    }

    public function testSetOfReadsTheLeadingKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f() RETURNS integer LANGUAGE sql AS 'SELECT 1'");
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        self::assertFalse($statement->result->setOf);
    }

    public function testNameAcceptsThreeComponents(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION db.app.f() RETURNS integer LANGUAGE sql AS 'SELECT 1'");
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        self::assertSame(['db', 'app', 'f'], $statement->name->parts);
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(["create or replace procedure p(a integer, b text default 'x') language sql as 'SELECT 1'", Statement\CreateProcedureStatement::class, 'CREATE OR REPLACE PROCEDURE "p"("a" integer, "b" text DEFAULT \'x\') LANGUAGE "sql" AS \'SELECT 1\''])]
    #[TestWith(["CREATE FUNCTION f(a integer, b integer) RETURNS SETOF integer LANGUAGE sql AS 'SELECT 1'", Statement\CreateFunctionStatement::class, 'CREATE FUNCTION "f"("a" integer, "b" integer) RETURNS SETOF integer LANGUAGE "sql" AS \'SELECT 1\''])]
    #[TestWith(["CREATE FUNCTION f(a integer) RETURNS TABLE (x integer, y text) LANGUAGE sql AS 'SELECT 1, 2'", Statement\CreateTableFunctionStatement::class, 'CREATE FUNCTION "f"("a" integer) RETURNS TABLE("x" integer, "y" text) LANGUAGE "sql" AS \'SELECT 1, 2\''])]
    public function testCreateKeepsEveryParameterAndColumn(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testHelpersReadParsedDeclarations(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("CREATE FUNCTION f(a integer, b text) RETURNS TABLE (x integer, y text) LANGUAGE sql AS 'SELECT 1, 2'"), ['CreateFunctionStmt'])[0];
        $parameters = RoutineDefinitions::parameters($source, false, $context);
        self::assertCount(2, $parameters);
        self::assertSame(['x', 'y'], array_column(RoutineDefinitions::columns($source, $parameters, $context), 'name'));
        $setOf = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("CREATE FUNCTION f() RETURNS SETOF integer LANGUAGE sql AS 'SELECT 1'"), ['func_type'])[0];
        self::assertTrue(RoutineDefinitions::setOf($setOf));
        self::assertTrue(RoutineDefinitions::result($setOf, $context)->setOf);
        self::assertSame(['f'], RoutineDefinitions::name(new \SqlSemantics\Model\Relation\QualifiedName(['f']), $source)->parts);
    }
}
