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
        self::assertSame('ALTER DOMAIN "d" ADD CONSTRAINT "positive" CHECK (("value" > 0)) NOT VALID', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD NOT NULL');
        self::assertInstanceOf(AddDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" ADD NOT NULL', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithDomainReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD NOT NULL');
        self::assertInstanceOf(AddDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "e" ADD NOT NULL', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withDomain(new QualifiedName(['e']))));
    }

    public function testWithConstraintReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD NOT NULL');
        self::assertInstanceOf(AddDomainConstraintStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "d" ADD CONSTRAINT "c" NOT NULL', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withConstraint(new Domain\DomainNotNull('c'))));
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
