<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Domain;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\AddDomainConstraintStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddDomainConstraintStatement::class)]
#[Medium]
final class AddDomainConstraintStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER DOMAIN d ADD CONSTRAINT positive CHECK (VALUE > 0) NOT VALID NO INHERIT');
        self::assertInstanceOf(AddDomainConstraintStatement::class, $statement);
        self::assertInstanceOf(Domain\DomainCheck::class, $statement->constraint);
        self::assertTrue($statement->notValid);
        self::assertSame('ALTER DOMAIN "d" ADD CONSTRAINT "positive" CHECK (("value" > 0)) NOT VALID', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD NOT NULL');
        self::assertInstanceOf(AddDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" ADD NOT NULL', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithDomainReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD NOT NULL');
        self::assertInstanceOf(AddDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "e" ADD NOT NULL', $statement->withDomain(new QualifiedName(['e']))->toString());
    }

    public function testWithConstraintReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD NOT NULL');
        self::assertInstanceOf(AddDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" ADD CONSTRAINT "c" NOT NULL', $statement->withConstraint(new Domain\DomainNotNull('c'))->toString());
    }

    public function testWithNotValidReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD CHECK (VALUE > 0) NOT VALID');
        self::assertInstanceOf(AddDomainConstraintStatement::class, $statement);
        self::assertFalse($statement->withNotValid(false)->notValid);
        self::assertTrue($statement->notValid);
    }

    public function testRejectsAnUnvalidatedNotNullConstraint(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD NOT NULL');
        self::assertInstanceOf(AddDomainConstraintStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withNotValid(true);
    }
}
