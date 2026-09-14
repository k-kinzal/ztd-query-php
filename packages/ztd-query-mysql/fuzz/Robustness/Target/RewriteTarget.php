<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Fuzz\Robustness\Invariant\ClassifyRewriteAgreementChecker;
use Fuzz\Robustness\Invariant\RewritePlanConsistencyChecker;
use ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\Rewrite\MySqlRewriter;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer;
use ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Shadow\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\Sql\MySqlParser;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Checks plan invariants and agreement between classification and rewriting.
 */
final class RewriteTarget
{
    /**
     * Verify one statement with independent, disposable shadow state.
     *
     * @throws Error
     */
    public function __invoke(string $sql): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $guard = new MySqlQueryGuard($parser);
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
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
        $rowsByTable = [
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
        foreach ($rowsByTable as $table => $rows) {
            $store->set($table, $rows);
        }
        $select = new SelectTransformer();
        $update = new UpdateTransformer($parser, $select);
        $delete = new DeleteTransformer($parser, $select);
        $transformer = new MySqlTransformer($parser, $select, new InsertTransformer($parser, $select), $update, $delete, new ReplaceTransformer($parser, $select));
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $update, $delete);
        $rewriter = new MySqlRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser);
        try {
            $checks = [
                new RewritePlanConsistencyChecker($rewriter),
                new ClassifyRewriteAgreementChecker($guard, $rewriter),
            ];
            foreach ($checks as $check) {
                $violation = $check->check($sql);
                if ($violation !== null) {
                    throw new Error((string) $violation);
                }
            }
        } finally {
            $store->clear();
            foreach ($registry->getAll() as $name => $definition) {
                $registry->unregister($name);
            }
        }
    }
}
