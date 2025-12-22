<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\TableDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(ColumnType::class)]
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
        );

        self::assertSame(['id', 'name'], $definition->columns);
        self::assertSame(['id' => 'INT', 'name' => 'VARCHAR(255)'], $definition->columnTypes);
        self::assertSame(['id'], $definition->primaryKeys);
        self::assertSame(['id'], $definition->notNullColumns);
        self::assertSame(['unique_name' => ['name']], $definition->uniqueConstraints);
        self::assertSame($typedColumns, $definition->typedColumns);
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
    }
}
