<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CastIdentity;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Cast\DropCastStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DropCastStatement::class)]
#[Medium]
final class DropCastStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP CAST (integer[] AS text) RESTRICT');
        self::assertInstanceOf(DropCastStatement::class, $statement);
        self::assertSame('integer[]', $statement->cast->source->name);
        self::assertFalse($statement->ifExists);
        self::assertSame(DropBehavior::Restrict, $statement->behavior);
        self::assertSame(StatementKind::Drop, $statement->kind);
        self::assertSame('DROP CAST(integer [] AS text) RESTRICT', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP CAST (integer AS text)');
        self::assertInstanceOf(DropCastStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DropCastStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql), $statement->cast);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP CAST (integer AS text)');
        self::assertInstanceOf(DropCastStatement::class, $statement);
        self::assertSame('DROP CAST(integer AS text)', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithCastReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP CAST (integer AS text)');
        self::assertInstanceOf(DropCastStatement::class, $statement);
        self::assertSame('DROP CAST(bigint AS text)', $statement->withCast(new CastIdentity(TypeDescriptor::builtin(Dialect::PostgreSql, 'bigint'), TypeDescriptor::builtin(Dialect::PostgreSql, 'text')))->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP CAST (integer AS text)');
        self::assertInstanceOf(DropCastStatement::class, $statement);
        self::assertSame('DROP CAST IF EXISTS(integer AS text)', $statement->withIfExists(true)->toString());
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP CAST (integer AS text)');
        self::assertInstanceOf(DropCastStatement::class, $statement);
        self::assertSame(DropBehavior::Cascade, $statement->withBehavior(DropBehavior::Cascade)->behavior);
        self::assertSame(DropBehavior::Default, $statement->behavior);
    }
}
