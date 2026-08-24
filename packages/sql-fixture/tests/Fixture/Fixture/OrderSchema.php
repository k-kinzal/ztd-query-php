<?php

declare(strict_types=1);

namespace Tests\Fixture\Fixture;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

/**
 * A minimal order table for the run-level tests.
 */
final class OrderSchema
{
    public static function create(): TableSchema
    {
        return new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
    }
}
