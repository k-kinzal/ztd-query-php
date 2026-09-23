<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Statement\Definition\PostgreSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Routine\Parameters::class)]
#[Medium]
final class ParametersTest extends TestCase
{
    public function testRoutineRetainsAllArgumentModesAndNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP FUNCTION f(a integer, b IN integer, OUT c text, d IN OUT integer, VARIADIC e text[])');
        self::assertInstanceOf(PostgreSql\DropFunctionsStatement::class, $statement);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[0]);
        $parameters = $statement->targets[0]->parameters;
        self::assertSame([Routine\ParameterMode::Implicit, Routine\ParameterMode::Input, Routine\ParameterMode::Output, Routine\ParameterMode::InputOutput, Routine\ParameterMode::Variadic], array_column($parameters, 'mode'));
        self::assertSame(['a', 'b', 'c', 'd', 'e'], array_column($parameters, 'name'));
        self::assertInstanceOf(\SqlSemantics\Type\TypeDescriptor::class, $parameters[4]->type);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\ArrayStorage::class, $parameters[4]->type->identity);
    }

    public function testTypeBindsColumnDeclarationsWithoutReadingValues(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id NUMERIC(10, 2) NOT NULL)');
        $statement = (new Binder($schema))->bind('DROP FUNCTION f(t.id%TYPE, SETOF text)');
        self::assertInstanceOf(PostgreSql\DropFunctionsStatement::class, $statement);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[0]);
        $parameters = $statement->targets[0]->parameters;
        self::assertInstanceOf(Routine\ColumnTypeReference::class, $parameters[0]->type);
        self::assertNotNull($parameters[0]->type->binding);
        self::assertSame($schema->tables[0]->columns[0]->type, $parameters[0]->type->binding->column->type);
        self::assertTrue($parameters[1]->setOf);
        self::assertSame('DROP FUNCTION "f"("t"."id" %TYPE, SETOF text)', $statement->toString());
    }

    public function testTypeRetainsAnUnresolvedReferenceWithItsDiagnosis(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('DROP PROCEDURE f(t.absent%TYPE)', strict: false);
        self::assertInstanceOf(PostgreSql\DropProceduresStatement::class, $statement);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[0]);
        self::assertInstanceOf(Routine\ColumnTypeReference::class, $statement->targets[0]->parameters[0]->type);
        self::assertNull($statement->targets[0]->parameters[0]->type->binding);
        self::assertSame(['unknown-column'], array_column($statement->diagnostics, 'reason'));
    }

    public function testTypeRejectsUnresolvedReferencesInStrictBinding(): void
    {
        $this->expectException(\SqlSemantics\SemanticException::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP FUNCTION f(absent.id%TYPE)');
    }

    public function testAggregateRejectsAnOutputArgumentAsInvalidSql(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('only input and variadic');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP AGGREGATE f(OUT integer)');
    }

}
