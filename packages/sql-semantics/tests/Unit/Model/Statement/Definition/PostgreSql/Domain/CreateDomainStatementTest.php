<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Domain;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\CreateDomainStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateDomainStatement::class)]
#[Medium]
final class CreateDomainStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE DOMAIN app.price numeric(10, 2) CONSTRAINT present NOT NULL DEFAULT 0 COLLATE "C" CHECK (VALUE >= 0)');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        self::assertSame(['app', 'price'], $statement->name->parts);
        self::assertSame('numeric', $statement->baseType->name);
        self::assertEquals(new QualifiedName(['C']), $statement->collation);
        self::assertEquals(new Domain\DomainNotNull('present'), $statement->constraints[0]);
        self::assertInstanceOf(Domain\DomainCheck::class, $statement->constraints[1]);
        self::assertSame('CREATE DOMAIN "app"."price" AS numeric(10, 2) DEFAULT 0 COLLATE "C" CONSTRAINT "present" NOT NULL CHECK (("value" >= 0))', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['app', 'e']));
        self::assertSame(['d'], $statement->name->parts);
        self::assertSame('CREATE DOMAIN "app"."e" AS integer', $changed->toString());
    }

    public function testWithBaseTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $changed = $statement->withBaseType(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
        self::assertSame('integer', $statement->baseType->name);
        self::assertSame('CREATE DOMAIN "d" AS text', $changed->toString());
    }

    public function testWithDefaultReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer DEFAULT 1');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $changed = $statement->withDefault(Expression::literal(2, Dialect::PostgreSql));
        self::assertNotNull($statement->default);
        self::assertSame('CREATE DOMAIN "d" AS integer DEFAULT 2', $changed->toString());
        self::assertSame('CREATE DOMAIN "d" AS integer', $statement->withDefault(null)->toString());
    }

    public function testWithCollationReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS text');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $changed = $statement->withCollation(new QualifiedName(['pg_catalog', 'C']));
        self::assertNull($statement->collation);
        self::assertSame('CREATE DOMAIN "d" AS text COLLATE "pg_catalog"."C"', $changed->toString());
    }

    public function testWithConstraintsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer NOT NULL');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $changed = $statement->withConstraints([new Domain\DomainNullable('open')]);
        self::assertCount(1, $statement->constraints);
        self::assertSame('CREATE DOMAIN "d" AS integer CONSTRAINT "open" NULL', $changed->toString());
    }

    public function testRejectsContradictoryNullability(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withConstraints([new Domain\DomainNullable(), new Domain\DomainNotNull()]);
    }

    public function testRejectsABaseTypeOfAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withBaseType(TypeDescriptor::builtin(Dialect::MySql, 'integer'));
    }

    public function testRejectsAnOverQualifiedCollation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withCollation(new QualifiedName(['a', 'b', 'c']));
    }
}
