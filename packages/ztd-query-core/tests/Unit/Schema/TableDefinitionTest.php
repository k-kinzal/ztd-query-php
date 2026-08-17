<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\IdentityGenerationStrategy;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TablePartitioning;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(ColumnType::class)]
#[UsesClass(ForeignKeyDefinition::class)]
#[UsesClass(TablePartitioning::class)]
#[CoversClass(TableDefinition::class)]
final class TableDefinitionTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $typedColumns = ['id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT')];

        $definition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            ['unique_name' => ['name']],
            $typedColumns,
            ['name' => "'anonymous'"],
            ['id' => IdentityGenerationStrategy::MaxValue],
            ['name' => "CONCAT('user-', id)"],
            ['fk_parent' => new ForeignKeyDefinition(['id'], 'parents', ['id'])],
            new TablePartitioning(['p0' => 'id < 10']),
        );

        self::assertSame(['id', 'name'], $definition->columns);
        self::assertSame(['id' => 'INT', 'name' => 'VARCHAR(255)'], $definition->columnTypes);
        self::assertSame(['id'], $definition->primaryKeys);
        self::assertSame(['id'], $definition->notNullColumns);
        self::assertSame(['unique_name' => ['name']], $definition->uniqueConstraints);
        self::assertSame($typedColumns, $definition->typedColumns);
        self::assertSame(['name' => "'anonymous'"], $definition->columnDefaults);
        self::assertSame(['id' => IdentityGenerationStrategy::MaxValue], $definition->identityStrategies);
        self::assertSame(['name' => "CONCAT('user-', id)"], $definition->generatedExpressions);
        self::assertSame(['fk_parent'], array_keys($definition->foreignKeys));
        self::assertSame('(id < 10)', $definition->partitioning?->predicateFor(['p0']));
    }

    public function testTypedColumnsDefaultsToEmpty(): void
    {
        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            [],
            [],
            [],
        );

        self::assertSame([], $definition->typedColumns);
        self::assertSame([], $definition->columnDefaults);
        self::assertSame([], $definition->identityStrategies);
        self::assertSame([], $definition->generatedExpressions);
        self::assertSame([], $definition->foreignKeys);
        self::assertNull($definition->partitioning);
    }

    public function testWithPartitioningPreservesSchemaAndReturnsNewDefinition(): void
    {
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $partitioning = new TablePartitioning(['p0' => 'id < 10']);

        $partitioned = $definition->withPartitioning($partitioning);

        self::assertNotSame($definition, $partitioned);
        self::assertSame($definition->columns, $partitioned->columns);
        self::assertSame($definition->primaryKeys, $partitioned->primaryKeys);
        self::assertSame($partitioning, $partitioned->partitioning);
    }
}
