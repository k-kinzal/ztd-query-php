<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\DropDomainConstraintStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropDomainConstraintStatement::class)]
#[Medium]
final class DropDomainConstraintStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT IF EXISTS c RESTRICT');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('c', $statement->constraint);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Restrict, $statement->behavior);
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT IF EXISTS "c" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT "c"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithDomainReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "e" DROP CONSTRAINT "c"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withDomain(new QualifiedName(['e']))));
    }

    public function testWithConstraintReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT "x"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withConstraint('x')));
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT IF EXISTS "c"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withIfExists(true)));
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT "c" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withBehavior(DropBehavior::Cascade)));
    }

    public function testRejectsAnEmptyConstraintName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withConstraint('');
    }
}
