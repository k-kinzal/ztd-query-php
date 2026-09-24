<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameDomainConstraintStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameDomainConstraintStatement::class)]
#[Medium]
final class RenameDomainConstraintStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.money RENAME CONSTRAINT positive TO non_negative', strict: false);
        self::assertInstanceOf(RenameDomainConstraintStatement::class, $statement);
        self::assertSame(['app', 'money'], $statement->domain->parts);
        self::assertSame('positive', $statement->constraint);
        self::assertSame('non_negative', $statement->newName);
        self::assertSame('ALTER DOMAIN "app"."money" RENAME CONSTRAINT "positive" TO "non_negative"', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.money RENAME CONSTRAINT positive TO non_negative', strict: false);
        self::assertInstanceOf(RenameDomainConstraintStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.money RENAME CONSTRAINT positive TO non_negative', strict: false);
        self::assertInstanceOf(RenameDomainConstraintStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithDomainReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.money RENAME CONSTRAINT positive TO non_negative', strict: false);
        self::assertInstanceOf(RenameDomainConstraintStatement::class, $statement);
        $changed = $statement->withDomain(new QualifiedName(['cash']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['app', 'money']), $statement->domain);
        self::assertEquals(new QualifiedName(['cash']), $changed->domain);
        self::assertStringContainsString('ALTER DOMAIN "cash"', $changed->toString());
    }

    public function testWithConstraintReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.money RENAME CONSTRAINT positive TO non_negative', strict: false);
        self::assertInstanceOf(RenameDomainConstraintStatement::class, $statement);
        $changed = $statement->withConstraint('c');
        self::assertNotSame($statement, $changed);
        self::assertEquals('positive', $statement->constraint);
        self::assertEquals('c', $changed->constraint);
        self::assertStringContainsString('RENAME CONSTRAINT "c"', $changed->toString());
    }

    public function testWithNewNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.money RENAME CONSTRAINT positive TO non_negative', strict: false);
        self::assertInstanceOf(RenameDomainConstraintStatement::class, $statement);
        $changed = $statement->withNewName('n');
        self::assertNotSame($statement, $changed);
        self::assertEquals('non_negative', $statement->newName);
        self::assertEquals('n', $changed->newName);
        self::assertStringContainsString('TO "n"', $changed->toString());
    }

    public function testRejectsAnOverQualifiedDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.money RENAME CONSTRAINT positive TO non_negative');
        self::assertInstanceOf(RenameDomainConstraintStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDomain(new QualifiedName(['db', 'app', 'money']));
    }
}
