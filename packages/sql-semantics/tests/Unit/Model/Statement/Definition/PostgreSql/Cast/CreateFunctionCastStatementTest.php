<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastContext;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Cast\CreateFunctionCastStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateFunctionCastStatement::class)]
#[Medium]
final class CreateFunctionCastStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE CAST (integer AS integer) WITH FUNCTION app.clamp(integer, integer, boolean) AS ASSIGNMENT');
        self::assertInstanceOf(CreateFunctionCastStatement::class, $statement);
        self::assertSame(['app', 'clamp'], $statement->function->name->parts);
        self::assertSame(CastContext::Assignment, $statement->castContext);
        self::assertSame('CREATE CAST(integer AS integer) WITH FUNCTION "app"."clamp"(integer, integer, boolean) AS ASSIGNMENT', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsATypeFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (integer AS text) WITH FUNCTION f');
        self::assertInstanceOf(CreateFunctionCastStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withSourceType(TypeDescriptor::builtin(Dialect::MySql, 'integer'));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (integer AS text) WITH FUNCTION f');
        self::assertInstanceOf(CreateFunctionCastStatement::class, $statement);
        self::assertSame('CREATE CAST(integer AS text) WITH FUNCTION "f"', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithSourceTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (integer AS text) WITH FUNCTION f');
        self::assertInstanceOf(CreateFunctionCastStatement::class, $statement);
        self::assertSame('bigint', $statement->withSourceType(TypeDescriptor::builtin(Dialect::PostgreSql, 'bigint'))->sourceType->name);
        self::assertSame('integer', $statement->sourceType->name);
    }

    public function testWithTargetTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (integer AS text) WITH FUNCTION f');
        self::assertInstanceOf(CreateFunctionCastStatement::class, $statement);
        self::assertSame('CREATE CAST(integer AS bigint) WITH FUNCTION "f"', $statement->withTargetType(TypeDescriptor::builtin(Dialect::PostgreSql, 'bigint'))->toString());
    }

    public function testWithFunctionReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (integer AS text) WITH FUNCTION f');
        self::assertInstanceOf(CreateFunctionCastStatement::class, $statement);
        self::assertSame('CREATE CAST(integer AS text) WITH FUNCTION "g"', $statement->withFunction(new RoutineByName(new QualifiedName(['g'])))->toString());
    }

    public function testWithCastContextReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (integer AS text) WITH FUNCTION f');
        self::assertInstanceOf(CreateFunctionCastStatement::class, $statement);
        self::assertSame(CastContext::Implicit, $statement->withCastContext(CastContext::Implicit)->castContext);
        self::assertSame(CastContext::Explicit, $statement->castContext);
    }
}
