<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\ValidateDomainConstraintStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ValidateDomainConstraintStatement::class)]
#[Medium]
final class ValidateDomainConstraintStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.d VALIDATE CONSTRAINT c');
        self::assertInstanceOf(ValidateDomainConstraintStatement::class, $statement);
        self::assertSame('c', $statement->constraint);
        self::assertSame('ALTER DOMAIN "app"."d" VALIDATE CONSTRAINT "c"', $statement->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d VALIDATE CONSTRAINT c');
        self::assertInstanceOf(ValidateDomainConstraintStatement::class, $statement);
        self::assertSame($statement->toString(), $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithDomainReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d VALIDATE CONSTRAINT c');
        self::assertInstanceOf(ValidateDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "e" VALIDATE CONSTRAINT "c"', $statement->withDomain(new QualifiedName(['e']))->toString());
    }

    public function testWithConstraintReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d VALIDATE CONSTRAINT c');
        self::assertInstanceOf(ValidateDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" VALIDATE CONSTRAINT "x"', $statement->withConstraint('x')->toString());
    }

    public function testRejectsAnEmptyConstraintName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d VALIDATE CONSTRAINT c');
        self::assertInstanceOf(ValidateDomainConstraintStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withConstraint('');
    }
}
