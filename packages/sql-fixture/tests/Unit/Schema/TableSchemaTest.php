<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
final class TableSchemaTest extends TestCase
{
    #[Test]
    public function getColumn(): void
    {
        $schema = new TableSchema('users', ['id' => new ColumnDefinition('id', 'INT'), 'name' => new ColumnDefinition('name', 'VARCHAR', length: 255), 'email' => new ColumnDefinition('email', 'VARCHAR', length: 255)], ['id']);
        $column = $schema->getColumn('id');
        self::assertNotNull($column);
        self::assertSame('id', $column->name);
        self::assertSame('INT', $column->type);
    }

    #[Test]
    public function getColumnReturnsNullForNonExistent(): void
    {
        self::assertNull((new TableSchema('users', ['id' => new ColumnDefinition('id', 'INT'), 'name' => new ColumnDefinition('name', 'VARCHAR', length: 255), 'email' => new ColumnDefinition('email', 'VARCHAR', length: 255)], ['id']))->getColumn('nonexistent'));
    }

    #[Test]
    public function hasColumn(): void
    {
        $schema = new TableSchema('users', ['id' => new ColumnDefinition('id', 'INT'), 'name' => new ColumnDefinition('name', 'VARCHAR', length: 255), 'email' => new ColumnDefinition('email', 'VARCHAR', length: 255)], ['id']);
        self::assertTrue($schema->hasColumn('id'));
        self::assertTrue($schema->hasColumn('name'));
        self::assertFalse($schema->hasColumn('nonexistent'));
    }

    #[Test]
    public function getColumnNames(): void
    {
        $names = (new TableSchema('users', ['id' => new ColumnDefinition('id', 'INT'), 'name' => new ColumnDefinition('name', 'VARCHAR', length: 255), 'email' => new ColumnDefinition('email', 'VARCHAR', length: 255)], ['id']))->getColumnNames();
        self::assertSame(['id', 'name', 'email'], $names);
    }

    #[Test]
    public function properties(): void
    {
        $schema = new TableSchema('users', ['id' => new ColumnDefinition('id', 'INT'), 'name' => new ColumnDefinition('name', 'VARCHAR', length: 255), 'email' => new ColumnDefinition('email', 'VARCHAR', length: 255)], ['id']);
        self::assertSame('users', $schema->tableName);
        self::assertCount(3, $schema->columns);
        self::assertSame(['id'], $schema->primaryKeys);
    }
}
