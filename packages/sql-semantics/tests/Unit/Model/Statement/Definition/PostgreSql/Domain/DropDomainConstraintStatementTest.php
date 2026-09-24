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
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT IF EXISTS "c" RESTRICT', $statement->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT "c"', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithDomainReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "e" DROP CONSTRAINT "c"', $statement->withDomain(new QualifiedName(['e']))->toString());
    }

    public function testWithConstraintReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT "x"', $statement->withConstraint('x')->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT IF EXISTS "c"', $statement->withIfExists(true)->toString());
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" DROP CONSTRAINT "c" CASCADE', $statement->withBehavior(DropBehavior::Cascade)->toString());
    }

    public function testRejectsAnEmptyConstraintName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP CONSTRAINT c');
        self::assertInstanceOf(DropDomainConstraintStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withConstraint('');
    }
}
