<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Extensibility\RoutineBodies;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Declaration;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\Parameter;
use SqlSemantics\Model\Statement\Definition\Routine as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineBodies::class)]
#[Medium]
final class RoutineBodiesTest extends TestCase
{
    #[TestWith(["CREATE FUNCTION f() RETURNS integer AS 'SELECT 1'"])]
    #[TestWith(['CREATE FUNCTION f() RETURNS integer LANGUAGE sql'])]
    #[TestWith(["CREATE FUNCTION f() RETURNS integer LANGUAGE sql AS 'SELECT 1' RETURN 1"])]
    #[TestWith(['CREATE FUNCTION f() RETURNS integer LANGUAGE plpgsql RETURN 1'])]
    #[TestWith(["CREATE FUNCTION f() RETURNS integer LANGUAGE internal AS 'a', 'b'"])]
    public function testImplementationRequiresOneBodyForItsLanguage(string $sql): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
            self::fail('The body must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::RoutineDefinition, $error->violation);
        }
    }

    public function testImplementationDefaultsAnInlineBodyToSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FUNCTION f() RETURNS integer TRANSFORM FOR TYPE integer RETURN 1');
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        self::assertSame(['sql', 'integer'], [$statement->implementation->language, $statement->implementation->transforms[0]->name]);
    }

    public function testItemsRejectsARepeatedItem(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f() RETURNS integer LANGUAGE sql AS 'a' AS 'b'");
    }

    public function testDefinitionKeepsTheStringSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FUNCTION f() RETURNS integer LANGUAGE c AS $$lib$$, E\'sym\'');
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        $body = $statement->implementation->body;
        self::assertInstanceOf(Declaration\LinkedBody::class, $body);
        self::assertSame(['$$lib$$', "E'sym'"], [$body->file->text, $body->symbol->text]);
    }

    public function testInlineDropsEmptyStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE PROCEDURE p() BEGIN ATOMIC ; SELECT 1; ; RETURN 2; END');
        self::assertInstanceOf(Statement\CreateProcedureStatement::class, $statement);
        $body = $statement->implementation->body;
        self::assertInstanceOf(Declaration\AtomicBody::class, $body);
        self::assertCount(2, $body->statements);
        self::assertInstanceOf(Declaration\ReturnBody::class, $body->statements[1]);
    }

    public function testReturnedResolvesNamedAndPositionalParameters(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FUNCTION f(n integer) RETURNS integer RETURN f.n');
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        $body = $statement->implementation->body;
        self::assertInstanceOf(Declaration\ReturnBody::class, $body);
        self::assertInstanceOf(ColumnReference::class, $body->value);
        self::assertSame('integer', $body->value->type->name);
    }

    public function testStatementPrefersColumnsOverParameters(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n TEXT)')))->bind('CREATE FUNCTION f(n integer) RETURNS text BEGIN ATOMIC SELECT n FROM t; END');
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        $body = $statement->implementation->body;
        self::assertInstanceOf(Declaration\AtomicBody::class, $body);
        $select = $body->statements[0];
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $select);
        self::assertSame('text', $select->outputs[0]->expression->type->name);
    }

    public function testTypesStopsAtAnUnresolvedType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FUNCTION f(a integer, OUT o text, b nosuch.c%TYPE, d text) RETURNS integer RETURN $1', strict: false);
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        self::assertSame(['integer'], array_map(static fn ($type): string => $type->name, RoutineBodies::types($statement->parameters)));
        $body = $statement->implementation->body;
        self::assertInstanceOf(Declaration\ReturnBody::class, $body);
        self::assertInstanceOf(Parameter::class, $body->value);
    }

    public function testTypeReadsTheResolvedColumnType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a BIGINT)')))->bind('CREATE FUNCTION f(a t.a%TYPE) RETURNS integer RETURN 1');
        self::assertInstanceOf(Statement\CreateFunctionStatement::class, $statement);
        self::assertSame('bigint', RoutineBodies::type($statement->parameters[0]->parameter->type)?->name);
    }

    public function testRelationNamesTheInputParametersAfterTheRoutine(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FUNCTION app.f(a integer, OUT b integer) RETURN 1');
        self::assertInstanceOf(Statement\CreateOutputFunctionStatement::class, $statement);
        $relation = RoutineBodies::relation($statement->origin, $statement->source, $statement->name, $statement->parameters, new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public')));
        self::assertSame(['f'], $relation->name->parts);
        self::assertSame(['a'], array_map(static fn ($column): string => $column->name, $relation->declaration->columns));
    }
}
