<?php

declare(strict_types=1);

namespace Tests\Fixture\CodeGen;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

/**
 * Schemas the code generation tests read from.
 */
final class SampleSchemas
{
    public static function orderDetail(): TableSchema
    {
        return new TableSchema('order_detail', [
            'id' => new ColumnDefinition('id', 'INT', nullable: false, autoIncrement: true),
            'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false),
            'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
        ], ['id']);
    }

    public static function order(): TableSchema
    {
        return new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', nullable: false, autoIncrement: true),
        ], ['id']);
    }

    public static function customer(): TableSchema
    {
        return new TableSchema('customer', [
            'id' => new ColumnDefinition('id', 'INT', nullable: false, autoIncrement: true),
        ], ['id']);
    }

    /**
     * @return list<TableSchema>
     */
    public static function pair(): array
    {
        return [self::order(), self::customer()];
    }
}
