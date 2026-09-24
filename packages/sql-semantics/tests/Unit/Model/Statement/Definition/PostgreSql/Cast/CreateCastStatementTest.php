<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastContext;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastMechanism;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Cast\CreateCastStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateCastStatement::class)]
#[Medium]
final class CreateCastStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE CAST (app.tag AS text) WITHOUT FUNCTION AS IMPLICIT');
        self::assertInstanceOf(CreateCastStatement::class, $statement);
        self::assertSame('app.tag', $statement->sourceType->name);
        self::assertSame('text', $statement->targetType->name);
        self::assertSame(CastMechanism::BinaryCoercible, $statement->mechanism);
        self::assertSame(CastContext::Implicit, $statement->castContext);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame('CREATE CAST("app"."tag" AS text) WITHOUT FUNCTION AS IMPLICIT', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testTypesRejectsTheSameTypeOnBothSides(): void
    {
        $this->expectException(InvalidStructure::class);
        CreateCastStatement::types(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), CastMechanism::InOut);
    }

    public function testTypesRejectsABinaryCoercibleArrayCast(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (integer[] AS text) WITH INOUT');
        self::assertInstanceOf(CreateCastStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withMechanism(CastMechanism::BinaryCoercible);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (bigint AS money) WITH INOUT');
        self::assertInstanceOf(CreateCastStatement::class, $statement);
        self::assertSame('CREATE CAST(bigint AS "money") WITH INOUT', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithSourceTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (bigint AS money) WITH INOUT');
        self::assertInstanceOf(CreateCastStatement::class, $statement);
        self::assertSame('CREATE CAST(integer AS "money") WITH INOUT', $statement->withSourceType(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))->toString());
        self::assertSame('bigint', $statement->sourceType->name);
    }

    public function testWithTargetTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (bigint AS money) WITH INOUT');
        self::assertInstanceOf(CreateCastStatement::class, $statement);
        self::assertSame('CREATE CAST(bigint AS text) WITH INOUT', $statement->withTargetType(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'))->toString());
    }

    public function testWithMechanismReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (bigint AS money) WITH INOUT');
        self::assertInstanceOf(CreateCastStatement::class, $statement);
        self::assertSame(CastMechanism::BinaryCoercible, $statement->withMechanism(CastMechanism::BinaryCoercible)->mechanism);
        self::assertSame(CastMechanism::InOut, $statement->mechanism);
    }

    public function testWithCastContextReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE CAST (bigint AS money) WITH INOUT');
        self::assertInstanceOf(CreateCastStatement::class, $statement);
        self::assertSame('CREATE CAST(bigint AS "money") WITH INOUT AS ASSIGNMENT', $statement->withCastContext(CastContext::Assignment)->toString());
    }
}
