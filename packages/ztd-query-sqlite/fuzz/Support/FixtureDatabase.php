<?php

declare(strict_types=1);

namespace Fuzz\Support;

use ZtdQuery\Platform\Sqlite\SqliteSchemaParser;
use ZtdQuery\Schema\TableDefinitionRegistry;

/**
 * Provides synthetic schemas and rows for independent robustness inputs.
 */
final class FixtureDatabase
{
    /**
     * Registers the synthetic relational schemas used by robustness targets.
     */
    public static function registerFixtureSchemas(TableDefinitionRegistry $registry, SqliteSchemaParser $schemaParser): void
    {
        $schemas = [
            'users' => 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT, status TEXT)',
            'orders' => 'CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, amount REAL, created_at TEXT)',
            'order_items' => 'CREATE TABLE order_items (order_id INTEGER NOT NULL, product_id INTEGER NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))',
            'products' => 'CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT NOT NULL, price REAL, category TEXT)',
        ];

        foreach ($schemas as $tableName => $createSql) {
            $definition = $schemaParser->parse($createSql);
            if ($definition !== null) {
                $registry->register($tableName, $definition);
            }
        }
    }

    /**
     * @return array<string, array<int, array<string, string|null>>>
     */
    public static function buildFixtureData(): array
    {
        return [
            'users' => [
                ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active'],
                ['id' => '2', 'name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'pending'],
                ['id' => '3', 'name' => 'Charlie', 'email' => null, 'status' => 'active'],
            ],
            'orders' => [
                ['id' => '1', 'user_id' => '1', 'amount' => '100.00', 'created_at' => '2024-01-01 00:00:00'],
                ['id' => '2', 'user_id' => '2', 'amount' => '250.50', 'created_at' => '2024-01-02 12:30:00'],
            ],
            'order_items' => [
                ['order_id' => '1', 'product_id' => '1', 'quantity' => '2'],
                ['order_id' => '1', 'product_id' => '2', 'quantity' => '1'],
                ['order_id' => '2', 'product_id' => '1', 'quantity' => '3'],
            ],
            'products' => [
                ['id' => '1', 'name' => 'Widget', 'price' => '19.99', 'category' => 'tools'],
                ['id' => '2', 'name' => 'Gadget', 'price' => '49.99', 'category' => 'electronics'],
            ],
        ];
    }

}
