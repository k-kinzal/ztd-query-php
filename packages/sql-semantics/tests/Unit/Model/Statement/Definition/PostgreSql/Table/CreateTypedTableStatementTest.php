<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\CreateTypedTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreateTypedTableStatement::class)]
#[Medium]
final class CreateTypedTableStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE UNLOGGED TABLE IF NOT EXISTS people OF app.person (name WITH OPTIONS NOT NULL DEFAULT \'x\', UNIQUE (name)) PARTITION BY HASH (name) TABLESPACE fast', strict: false);
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        self::assertSame(['people'], $statement->name->parts);
        self::assertSame(['app', 'person'], $statement->type->parts);
        self::assertSame(Nullability::NotNull, $statement->columns[0]->nullability);
        self::assertCount(1, $statement->constraints);
        self::assertSame(Persistence::Unlogged, $statement->properties->persistence);
        self::assertSame('fast', $statement->properties->tablespace);
        self::assertTrue($statement->ifNotExists);
        $expected = 'CREATE UNLOGGED TABLE IF NOT EXISTS "people" OF "app"."person"("name" WITH OPTIONS NOT NULL DEFAULT \'x\', UNIQUE("name")) PARTITION BY HASH("name") TABLESPACE "fast"';
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    public function testRejectsAnotherDatabaseLanguage(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new CreateTypedTableStatement($origin, new QualifiedName(['t']), new QualifiedName(['ty']));
    }

    public function testRejectsATypeNameWithThreeComponents(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new CreateTypedTableStatement($origin, new QualifiedName(['t']), new QualifiedName(['d', 's', 'ty']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t OF ty');
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE TABLE "t" OF "ty"', $copy->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t OF ty');
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['app', 'u']));
        self::assertSame(['t'], $statement->name->parts);
        self::assertSame('CREATE TABLE "app"."u" OF "ty"', $changed->toString());
    }

    public function testWithTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t OF ty');
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        $changed = $statement->withType(new QualifiedName(['app', 'other']));
        self::assertSame(['ty'], $statement->type->parts);
        self::assertSame('CREATE TABLE "t" OF "app"."other"', $changed->toString());
    }

    public function testWithColumnsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t OF ty');
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        $changed = $statement->withColumns([new PartitionColumn('a', Nullability::NotNull)]);
        self::assertSame([], $statement->columns);
        self::assertSame('CREATE TABLE "t" OF "ty"("a" WITH OPTIONS NOT NULL)', $changed->toString());
    }

    public function testWithConstraintsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t OF ty (UNIQUE (a))', strict: false);
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        $changed = $statement->withConstraints([]);
        self::assertCount(1, $statement->constraints);
        self::assertSame('CREATE TABLE "t" OF "ty"', $changed->toString());
    }

    public function testWithPropertiesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t OF ty');
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        $changed = $statement->withProperties(new PostgreSqlProperties(Persistence::Temporary, accessMethod: 'heap'));
        self::assertNull($statement->properties->accessMethod);
        self::assertSame('CREATE TEMPORARY TABLE "t" OF "ty" USING "heap"', $changed->toString());
    }

    public function testWithIfNotExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t OF ty');
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        $changed = $statement->withIfNotExists(true);
        self::assertFalse($statement->ifNotExists);
        self::assertSame('CREATE TABLE IF NOT EXISTS "t" OF "ty"', $changed->toString());
    }

    public function testWithExclusionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t OF ty (EXCLUDE (a WITH =))', strict: false);
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        $changed = $statement->withExclusions([]);
        self::assertCount(1, $statement->exclusions);
        self::assertSame('CREATE TABLE "t" OF "ty"', $changed->toString());
    }
}
