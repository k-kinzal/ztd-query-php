<?php

declare(strict_types=1);

namespace Fuzz\Robustness;

use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Creates isolated schema and row state for one generated statement.
 */
final class RewriteFixture
{
    /**
     * Store owned by this single-input fixture.
     */
    public readonly ShadowStore $store;
    /**
     * Registry owned by this single-input fixture.
     */
    public readonly TableDefinitionRegistry $registry;
    /**
     * Guard owned by this single-input fixture.
     */
    public readonly MySqlQueryGuard $guard;
    /**
     * Rewriter owned by this single-input fixture.
     */
    public readonly MySqlRewriter $rewriter;

    /**
     * Build a fresh rewrite graph so mutations cannot affect later fuzz inputs.
     */
    public function __construct()
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $this->guard = new MySqlQueryGuard($parser);
        $this->store = new ShadowStore();
        $this->registry = new TableDefinitionRegistry();
        $this->registerFixtureSchemas($this->registry, $schemaParser);
        foreach ($this->buildFixtureData() as $table => $rows) {
            $this->store->set($table, $rows);
        }
        $select = new SelectTransformer();
        $update = new UpdateTransformer($parser, $select);
        $delete = new DeleteTransformer($parser, $select);
        $transformer = new MySqlTransformer($parser, $select, new InsertTransformer($parser, $select), $update, $delete, new ReplaceTransformer($parser, $select));
        $mutationResolver = new MySqlMutationResolver($this->store, $this->registry, $schemaParser, $update, $delete);
        $this->rewriter = new MySqlRewriter($this->guard, $this->store, $this->registry, $transformer, $mutationResolver, $parser);
    }

    /**
     * Discard all mutable rows and definitions after an input, including rejected inputs.
     */
    public function reset(): void
    {
        $this->store->clear();
        foreach ($this->registry->getAll() as $name => $definition) {
            $this->registry->unregister($name);
        }
    }

    /**
     * Register Fixture Schemas for the supplied MySQL input.
     */
    public function registerFixtureSchemas(TableDefinitionRegistry $registry, MySqlSchemaParser $schemaParser): void
    {
        $schemas = [
            'users' => 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), status VARCHAR(50))',
            'orders' => 'CREATE TABLE orders (id INT PRIMARY KEY, user_id INT NOT NULL, amount DECIMAL(10,2), created_at DATETIME)',
            'order_items' => 'CREATE TABLE order_items (order_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))',
            'products' => 'CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, price DECIMAL(10,2), category VARCHAR(100))',
            'events' => 'CREATE TABLE events (id INT PRIMARY KEY, event_date DATE) PARTITION BY RANGE (YEAR(event_date)) (PARTITION p2023 VALUES LESS THAN (2024), PARTITION p2024 VALUES LESS THAN (2025), PARTITION pmax VALUES LESS THAN MAXVALUE)',
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
    public function buildFixtureData(): array
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
            'events' => [
                ['id' => '1', 'event_date' => '2023-06-01'],
                ['id' => '2', 'event_date' => '2024-06-01'],
            ],
        ];
    }
}
