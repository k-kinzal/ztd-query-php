<?php

declare(strict_types=1);

namespace Fuzz\Fixture;

use ZtdQuery\Platform\Postgres\PgSqlSchemaParser;
use ZtdQuery\Schema\PartialUniqueIndex;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Seeds the schema and rows used by rewrite and mutation invariants.
 */
final class FixtureDatabase
{
    /**
     * Register fixture schemas.
     */
    public function registerFixtureSchemas(TableDefinitionRegistry $registry, PgSqlSchemaParser $schemaParser): void
    {
        $schemas = ['users' => 'CREATE TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), status VARCHAR(50))', 'orders' => 'CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, amount NUMERIC(10,2), created_at TIMESTAMP)', 'order_items' => 'CREATE TABLE order_items (order_id INTEGER NOT NULL, product_id INTEGER NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))', 'products' => 'CREATE TABLE products (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL, price NUMERIC(10,2), category VARCHAR(100))', 'logs' => 'CREATE TABLE logs (id INTEGER NOT NULL, log_date DATE NOT NULL, level TEXT NOT NULL, PRIMARY KEY (id, log_date)) PARTITION BY RANGE (log_date)', 'contacts' => 'CREATE TABLE contacts (id INTEGER PRIMARY KEY, age "ztd_fuzz"."positive_int", satisfaction "ztd_fuzz"."percentage")'];
        foreach ($schemas as $tableName => $createSql) {
            $definition = $schemaParser->parse($createSql);
            if ($definition !== null) {
                if ($tableName === 'users') {
                    $definition = $definition->withPartialUniqueIndex(new PartialUniqueIndex('users_active_email', ['email'], "status = 'active'"));
                }
                $registry->register($tableName, $definition);
            }
        }
    }

    /**
     * Populate fixture data.
     */
    public function populateFixtureData(ShadowStore $store): void
    {
        $store->set('users', [['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active'], ['id' => '2', 'name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'pending'], ['id' => '3', 'name' => 'Charlie', 'email' => null, 'status' => 'active']]);
        $store->set('orders', [['id' => '1', 'user_id' => '1', 'amount' => '100.00', 'created_at' => '2024-01-01 00:00:00'], ['id' => '2', 'user_id' => '2', 'amount' => '250.50', 'created_at' => '2024-01-02 12:30:00']]);
        $store->set('order_items', [['order_id' => '1', 'product_id' => '1', 'quantity' => '2'], ['order_id' => '1', 'product_id' => '2', 'quantity' => '1'], ['order_id' => '2', 'product_id' => '1', 'quantity' => '3']]);
        $store->set('products', [['id' => '1', 'name' => 'Widget', 'price' => '19.99', 'category' => 'tools'], ['id' => '2', 'name' => 'Gadget', 'price' => '49.99', 'category' => 'electronics']]);
        $store->set('logs', [['id' => '1', 'log_date' => '2024-05-01', 'level' => 'INFO'], ['id' => '2', 'log_date' => '2025-05-01', 'level' => 'WARN']]);
        $store->set('contacts', [['id' => '1', 'age' => '30', 'satisfaction' => '85.50']]);
    }
}
