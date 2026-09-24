<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignPartitionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateForeignPartitionStatement::class)]
#[Medium]
final class CreateForeignPartitionStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t (CHECK (id > 0)) FOR VALUES IN (1, 2) SERVER remote OPTIONS (a $$b$$)', strict: false);
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        self::assertSame(['ft'], $statement->name->parts);
        self::assertSame(['t'], $statement->parent->parts);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Relation\Partition\ListPartitionBound::class, $statement->bound);
        self::assertSame('remote', $statement->server);
        self::assertCount(1, $statement->options);
        self::assertCount(1, $statement->constraints);
        self::assertSame([], $statement->columns);
        self::assertFalse($statement->ifNotExists);
        self::assertSame('CREATE FOREIGN TABLE "ft" PARTITION OF "t"(CHECK (("id" > 0))) FOR VALUES IN(1, 2) SERVER "remote" OPTIONS("a" $$b$$)', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t (CHECK (id > 0)) FOR VALUES IN (1, 2) SERVER remote OPTIONS (a $$b$$)', strict: false);
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t (CHECK (id > 0)) FOR VALUES IN (1, 2) SERVER remote OPTIONS (a $$b$$)', strict: false);
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t (CHECK (id > 0)) FOR VALUES IN (1, 2) SERVER remote OPTIONS (a $$b$$)', strict: false);
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['app', 'ft2']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['ft']), $statement->name);
        self::assertEquals(new QualifiedName(['app', 'ft2']), $changed->name);
        self::assertStringContainsString('TABLE "app"."ft2" PARTITION', $changed->toString());
    }

    public function testWithParentReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t (CHECK (id > 0)) FOR VALUES IN (1, 2) SERVER remote OPTIONS (a $$b$$)', strict: false);
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        $changed = $statement->withParent(new QualifiedName(['t']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['t']), $statement->parent);
        self::assertEquals(new QualifiedName(['t']), $changed->parent);
        self::assertStringContainsString('PARTITION OF "t"', $changed->toString());
    }

    public function testWithBoundReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t (CHECK (id > 0)) FOR VALUES IN (1, 2) SERVER remote OPTIONS (a $$b$$)', strict: false);
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        $changed = $statement->withBound(new \SqlSemantics\Model\Definition\Relation\Partition\DefaultPartitionBound());
        self::assertNotSame($statement, $changed);
        self::assertEquals($statement->bound, $statement->bound);
        self::assertEquals(new \SqlSemantics\Model\Definition\Relation\Partition\DefaultPartitionBound(), $changed->bound);
        self::assertStringContainsString(') DEFAULT SERVER', $changed->toString());
    }

    public function testWithServerReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t (CHECK (id > 0)) FOR VALUES IN (1, 2) SERVER remote OPTIONS (a $$b$$)', strict: false);
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        $changed = $statement->withServer('other');
        self::assertNotSame($statement, $changed);
        self::assertEquals('remote', $statement->server);
        self::assertEquals('other', $changed->server);
        self::assertStringContainsString('SERVER "other"', $changed->toString());
    }

    public function testWithIfNotExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t (CHECK (id > 0)) FOR VALUES IN (1, 2) SERVER remote OPTIONS (a $$b$$)', strict: false);
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        $changed = $statement->withIfNotExists(true);
        self::assertNotSame($statement, $changed);
        self::assertEquals(false, $statement->ifNotExists);
        self::assertEquals(true, $changed->ifNotExists);
        self::assertStringContainsString('IF NOT EXISTS "ft"', $changed->toString());
    }

    public function testBindsColumnOverridesWithoutTypes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, b INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t (id WITH OPTIONS NOT NULL DEFAULT 1 COLLATE "C", b GENERATED ALWAYS AS (1 + 2) STORED) DEFAULT SERVER s');
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        self::assertSame('id', $statement->columns[0]->column);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $statement->columns[0]->nullability);
        self::assertSame(['C'], $statement->columns[0]->collation?->parts);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\ComputedColumn::class, $statement->columns[1]->generation);
        self::assertSame('CREATE FOREIGN TABLE "ft" PARTITION OF "t"("id" WITH OPTIONS COLLATE "C" NOT NULL DEFAULT 1, "b" WITH OPTIONS GENERATED ALWAYS AS((1 + 2)) STORED) DEFAULT SERVER "s"', $statement->toString());
    }

    public function testRejectsAnEmptyServer(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF t DEFAULT SERVER s');
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withServer('');
    }
}
