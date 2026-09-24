<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Definition\Relation\Partition\DefaultPartitionBound;
use SqlSemantics\Model\Definition\Relation\Partition\RangePartitionBound;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\CreatePartitionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreatePartitionStatement::class)]
#[Medium]
final class CreatePartitionStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER, at DATE) PARTITION BY RANGE (id)'));
        $statement = $binder->bind('CREATE TEMP TABLE IF NOT EXISTS c PARTITION OF p (id WITH OPTIONS NOT NULL, CONSTRAINT k CHECK (id > 0)) FOR VALUES FROM (1) TO (10) PARTITION BY LIST (at) ON COMMIT DROP');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        self::assertSame(['c'], $statement->name->parts);
        self::assertSame(['p'], $statement->parent->parts);
        self::assertInstanceOf(RangePartitionBound::class, $statement->bound);
        self::assertSame('id', $statement->columns[0]->column);
        self::assertSame(Nullability::NotNull, $statement->columns[0]->nullability);
        self::assertSame('k', $statement->constraints[0]->name);
        self::assertSame(Persistence::Temporary, $statement->properties->persistence);
        self::assertSame(\SqlSemantics\Schema\Partition\PartitionStrategy::List, $statement->properties->partitioning?->strategy);
        self::assertTrue($statement->ifNotExists);
        $expected = 'CREATE TEMPORARY TABLE IF NOT EXISTS "c" PARTITION OF "p"("id" WITH OPTIONS NOT NULL, CONSTRAINT "k" CHECK (("id" > 0))) FOR VALUES FROM(1) TO(10) PARTITION BY LIST("at") ON COMMIT DROP';
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testRejectsAnotherDatabaseLanguage(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new CreatePartitionStatement($origin, new QualifiedName(['c']), new QualifiedName(['p']), new DefaultPartitionBound());
    }

    public function testRejectsAColumnOverriddenTwice(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new CreatePartitionStatement($origin, new QualifiedName(['c']), new QualifiedName(['p']), new DefaultPartitionBound(), [new PartitionColumn('id'), new PartitionColumn('id', Nullability::NotNull)]);
    }

    public function testRejectsInheritance(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new CreatePartitionStatement($origin, new QualifiedName(['c']), new QualifiedName(['p']), new DefaultPartitionBound(), properties: new PostgreSqlProperties(parents: [new QualifiedName(['a'])]));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)')))->bind('CREATE TABLE c PARTITION OF p DEFAULT');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE TABLE "c" PARTITION OF "p" DEFAULT', $copy->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)')))->bind('CREATE TABLE c PARTITION OF p DEFAULT');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['app', 'c2']));
        self::assertSame(['c'], $statement->name->parts);
        self::assertSame('CREATE TABLE "app"."c2" PARTITION OF "p" DEFAULT', $changed->toString());
    }

    public function testWithParentReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER); CREATE TABLE q(id INTEGER)')))->bind('CREATE TABLE c PARTITION OF p DEFAULT');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        $changed = $statement->withParent(new QualifiedName(['q']));
        self::assertSame(['p'], $statement->parent->parts);
        self::assertSame('CREATE TABLE "c" PARTITION OF "q" DEFAULT', $changed->toString());
    }

    public function testWithBoundReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)')))->bind('CREATE TABLE c PARTITION OF p FOR VALUES IN (1)');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        $changed = $statement->withBound(new DefaultPartitionBound());
        self::assertSame('CREATE TABLE "c" PARTITION OF "p" FOR VALUES IN(1)', $statement->toString());
        self::assertSame('CREATE TABLE "c" PARTITION OF "p" DEFAULT', $changed->toString());
    }

    public function testWithColumnsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)')))->bind('CREATE TABLE c PARTITION OF p DEFAULT');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        $changed = $statement->withColumns([new PartitionColumn('id', Nullability::NotNull)]);
        self::assertSame([], $statement->columns);
        self::assertSame('CREATE TABLE "c" PARTITION OF "p"("id" WITH OPTIONS NOT NULL) DEFAULT', $changed->toString());
    }

    public function testWithConstraintsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)')))->bind('CREATE TABLE c PARTITION OF p (CHECK (id > 0)) DEFAULT');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        $changed = $statement->withConstraints([]);
        self::assertCount(1, $statement->constraints);
        self::assertSame('CREATE TABLE "c" PARTITION OF "p" DEFAULT', $changed->toString());
    }

    public function testWithPropertiesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)')))->bind('CREATE TABLE c PARTITION OF p DEFAULT');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        $changed = $statement->withProperties(new PostgreSqlProperties(Persistence::Unlogged, tablespace: 'fast'));
        self::assertSame(Persistence::Permanent, $statement->properties->persistence);
        self::assertSame('CREATE UNLOGGED TABLE "c" PARTITION OF "p" DEFAULT TABLESPACE "fast"', $changed->toString());
    }

    public function testWithIfNotExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)')))->bind('CREATE TABLE c PARTITION OF p DEFAULT');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        $changed = $statement->withIfNotExists(true);
        self::assertFalse($statement->ifNotExists);
        self::assertSame('CREATE TABLE IF NOT EXISTS "c" PARTITION OF "p" DEFAULT', $changed->toString());
    }

    public function testWithExclusionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)')))->bind('CREATE TABLE c PARTITION OF p (EXCLUDE (id WITH =)) DEFAULT', strict: false);
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        $changed = $statement->withExclusions([]);
        self::assertCount(1, $statement->exclusions);
        self::assertSame('CREATE TABLE "c" PARTITION OF "p" DEFAULT', $changed->toString());
    }
}
