<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Schema\Copy\PostgreSqlCopy;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(PostgreSqlCopy::class)]
#[Medium]
final class PostgreSqlCopyTest extends TestCase
{
    public function testFormGivesAPartitionItsParentColumnsWithOverrides(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p (id INTEGER, v TEXT, CHECK (id > 0)) PARTITION BY LIST (id)', 'CREATE TABLE c PARTITION OF p (v WITH OPTIONS NOT NULL, UNIQUE (id)) FOR VALUES IN (1) PARTITION BY HASH (v)');
        $partition = $schema->tables[1];
        self::assertSame('c', $partition->name);
        self::assertSame(['id', 'v'], array_map(static fn (ColumnDefinition $column): string => $column->name, $partition->columns));
        self::assertSame(Nullability::NotNull, $partition->columns[1]->nullability);
        self::assertCount(2, $partition->constraints);
        self::assertInstanceOf(\SqlSemantics\Schema\Table\PostgreSqlProperties::class, $partition->properties);
        self::assertSame(\SqlSemantics\Schema\Partition\PartitionStrategy::Hash, $partition->properties->partitioning?->strategy);
    }

    public function testFormLeavesTheColumnsOfATypedTableUnresolved(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE people OF app.person')->tables[0];
        self::assertSame('people', $table->name);
        self::assertSame([], $table->columns);
        self::assertFalse($table->resolved);
    }

    public function testOverrideAppliesNullabilityDefaultAndCollation(): void
    {
        $column = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p (v TEXT DEFAULT \'a\')')->tables[0]->columns[0];
        $kept = PostgreSqlCopy::override($column, [new PartitionColumn('other', Nullability::NotNull)]);
        self::assertSame($column, $kept);
        $changed = PostgreSqlCopy::override($column, [new PartitionColumn('v', Nullability::NotNull, collation: new QualifiedName(['C']))]);
        self::assertSame(Nullability::NotNull, $changed->nullability);
        self::assertSame($column->generation, $changed->generation);
        self::assertSame(['C'], $changed->attributes->collation?->parts);
    }

    public function testLayoutPlacesInheritedColumnsFirstAndTemplatesWhereWritten(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a (x INTEGER, w INTEGER)', 'CREATE TABLE q (x INTEGER, id INTEGER) INHERITS (a)', 'CREATE TABLE l (k INTEGER, LIKE a, z INTEGER)');
        self::assertSame(['x', 'w', 'id'], array_map(static fn (ColumnDefinition $column): string => $column->name, $schema->tables[1]->columns));
        self::assertSame(['k', 'x', 'w', 'z'], array_map(static fn (ColumnDefinition $column): string => $column->name, $schema->tables[2]->columns));
    }

    public function testMergeKeepsTheFirstColumnOfEachName(): void
    {
        $columns = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a (x INTEGER, y INTEGER)', 'CREATE TABLE b (y TEXT, z TEXT)')->tables;
        $merged = PostgreSqlCopy::merge($columns[0]->columns, $columns[1]->columns);
        self::assertSame(['x', 'y', 'z'], array_map(static fn (ColumnDefinition $column): string => $column->name, $merged));
        self::assertSame('integer', $merged[1]->type->name);
    }
}
