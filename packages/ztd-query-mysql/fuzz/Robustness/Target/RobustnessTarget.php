<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Faker\Generator;
use Fuzz\Robustness\Invariant\ClassifyDeterministicChecker;
use Fuzz\Robustness\Invariant\ClassifyNeverThrowsChecker;
use Fuzz\Robustness\Invariant\ClassifyRewriteAgreementChecker;
use Fuzz\Robustness\Invariant\InvariantChecker;
use Fuzz\Robustness\Invariant\RewriteExceptionTypeChecker;
use Fuzz\Robustness\Invariant\RewritePlanConsistencyChecker;
use Fuzz\Robustness\Invariant\ShadowStoreConsistencyChecker;
use SqlFaker\MySqlProvider;
use ZtdQuery\Exception\SimulationException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

final class RobustnessTarget
{
    private Generator $faker;
    private MySqlProvider $provider;
    private MySqlRewriter $rewriter;
    private ShadowStore $shadowStore;
    private ShadowStoreConsistencyChecker $storeChecker;
    /** @var array<int, InvariantChecker> */
    private array $checkers;
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $fixtureData;

    public function __construct(Generator $faker, MySqlProvider $provider)
    {
        $this->faker = $faker;
        $this->provider = $provider;

        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $guard = new MySqlQueryGuard($parser);
        $this->shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $this->registerFixtureSchemas($registry, $schemaParser);
        $this->fixtureData = $this->buildFixtureData();
        $this->resetShadowStore();

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($this->shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $this->rewriter = new MySqlRewriter($guard, $this->shadowStore, $registry, $transformer, $mutationResolver, $parser);
        $this->storeChecker = new ShadowStoreConsistencyChecker($this->shadowStore);

        $this->checkers = [
            new ClassifyNeverThrowsChecker($guard),
            new ClassifyDeterministicChecker($guard),
            new RewriteExceptionTypeChecker($this->rewriter),
            new RewritePlanConsistencyChecker($this->rewriter),
            new ClassifyRewriteAgreementChecker($guard, $this->rewriter),
        ];
    }

    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $sql = $this->selectGenerator($input)();

        foreach ($this->checkers as $checker) {
            $violation = $checker->check($sql);
            if ($violation !== null) {
                throw new \Error("Invariant violation: seed=$seed\n$violation");
            }
        }

        try {
            try {
                $plan = $this->rewriter->rewrite($sql);
            } catch (UnsupportedSqlException | UnknownSchemaException) {
                return;
            }

            if ($plan->kind() === QueryKind::WRITE_SIMULATED || $plan->kind() === QueryKind::DDL_SIMULATED) {
                $mutation = $plan->mutation();
                if ($mutation !== null) {
                    try {
                        $mutation->apply($this->shadowStore, []);
                    } catch (SimulationException) {
                    }

                    $violation = $this->storeChecker->check($sql);
                    if ($violation !== null) {
                        throw new \Error("Invariant violation: seed=$seed\n$violation");
                    }
                }
            }
        } finally {
            $this->resetShadowStore();
        }
    }

    private function resetShadowStore(): void
    {
        $this->shadowStore->clear();
        foreach ($this->fixtureData as $table => $rows) {
            $this->shadowStore->set($table, $rows);
        }
    }

    private function registerFixtureSchemas(TableDefinitionRegistry $registry, MySqlSchemaParser $schemaParser): void
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
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildFixtureData(): array
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

    /**
     * @return callable(): string
     */
    private function selectGenerator(string $input): callable
    {
        $generators = [
            fn () => $this->provider->sql(maxDepth: 8),
            fn () => $this->provider->selectStatement(maxDepth: 8),
            fn () => $this->provider->insertStatement(maxDepth: 8),
            fn (): string => (ord($input[1] ?? "\0") % 2) === 0
                ? $this->provider->updateStatement(maxDepth: 8)
                : $this->provider->multiTableUpdateStatement(maxDepth: 8),
            fn (): string => (ord($input[1] ?? "\0") % 2) === 0
                ? $this->provider->deleteStatement(maxDepth: 8)
                : $this->provider->multiTableDeleteStatement(maxDepth: 8),
            fn () => $this->provider->createTableStatement(maxDepth: 5),
            fn () => $this->provider->alterTableStatement(maxDepth: 5),
            fn () => $this->provider->replaceStatement(maxDepth: 5),
            fn () => $this->provider->truncateStatement(maxDepth: 3),
            fn (): string => 'EXPLAIN SELECT * FROM users',
            fn (): string => 'SELECT * FROM app.users',
            fn (): string => "UPDATE users SET status = {$this->provider->quotedHexLiteral()} WHERE id = 1",
            fn (): string => "UPDATE orders SET created_at = created_at + INTERVAL {$this->provider->integerLiteral(1, 30)} DAY WHERE id = 1",
            fn (): string => 'UPDATE users SET status = 0 WHERE CASE WHEN id > 1 THEN 1 ELSE 0 END = 1',
            fn (): string => 'DELETE FROM users WHERE CASE WHEN id > 1 THEN 1 ELSE 0 END = 1',
            fn (): string => "CREATE TABLE child (id INT, parent_id INT, {$this->provider->foreignKeyConstraint()})",
            fn (): string => $this->provider->updateJoinDerivedStatement(),
            fn (): string => $this->provider->insertSelectCompoundStatement(),
            fn (): string => $this->provider->insertRowAliasUpsertStatement(),
            fn (): string => $this->provider->partitionSelectStatement(),
            fn (): string => $this->provider->loadDataStatement(maxDepth: 8),
            fn (): string => $this->provider->fullTextSearchStatement(),
        ];

        $index = ord($input[0] ?? "\0") % count($generators);
        return $generators[$index];
    }
}
