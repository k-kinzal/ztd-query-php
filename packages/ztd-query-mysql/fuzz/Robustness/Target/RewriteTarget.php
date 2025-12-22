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
use SqlFaker\MySqlProvider;
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
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

final class RewriteTarget
{
    private Generator $faker;
    private MySqlProvider $provider;
    /** @var array<int, InvariantChecker> */
    private array $checkers;

    public function __construct(Generator $faker, MySqlProvider $provider)
    {
        $this->faker = $faker;
        $this->provider = $provider;

        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $guard = new MySqlQueryGuard($parser);
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();

        $this->registerFixtureSchemas($registry, $schemaParser);
        $this->populateFixtureData($shadowStore);

        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter($guard, $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        $this->checkers = [
            new ClassifyNeverThrowsChecker($guard),
            new ClassifyDeterministicChecker($guard),
            new RewriteExceptionTypeChecker($rewriter),
            new RewritePlanConsistencyChecker($rewriter),
            new ClassifyRewriteAgreementChecker($guard, $rewriter),
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
    }

    private function registerFixtureSchemas(TableDefinitionRegistry $registry, MySqlSchemaParser $schemaParser): void
    {
        $schemas = [
            'users' => 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), status VARCHAR(50))',
            'orders' => 'CREATE TABLE orders (id INT PRIMARY KEY, user_id INT NOT NULL, amount DECIMAL(10,2), created_at DATETIME)',
            'order_items' => 'CREATE TABLE order_items (order_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))',
            'products' => 'CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, price DECIMAL(10,2), category VARCHAR(100))',
        ];

        foreach ($schemas as $tableName => $createSql) {
            $definition = $schemaParser->parse($createSql);
            if ($definition !== null) {
                $registry->register($tableName, $definition);
            }
        }
    }

    private function populateFixtureData(ShadowStore $store): void
    {
        $store->set('users', [
            ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active'],
            ['id' => '2', 'name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'pending'],
            ['id' => '3', 'name' => 'Charlie', 'email' => null, 'status' => 'active'],
        ]);
        $store->set('orders', [
            ['id' => '1', 'user_id' => '1', 'amount' => '100.00', 'created_at' => '2024-01-01 00:00:00'],
            ['id' => '2', 'user_id' => '2', 'amount' => '250.50', 'created_at' => '2024-01-02 12:30:00'],
        ]);
        $store->set('order_items', [
            ['order_id' => '1', 'product_id' => '1', 'quantity' => '2'],
            ['order_id' => '1', 'product_id' => '2', 'quantity' => '1'],
            ['order_id' => '2', 'product_id' => '1', 'quantity' => '3'],
        ]);
        $store->set('products', [
            ['id' => '1', 'name' => 'Widget', 'price' => '19.99', 'category' => 'tools'],
            ['id' => '2', 'name' => 'Gadget', 'price' => '49.99', 'category' => 'electronics'],
        ]);
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
            fn () => $this->provider->updateStatement(maxDepth: 8),
            fn () => $this->provider->deleteStatement(maxDepth: 8),
            fn () => $this->provider->createTableStatement(maxDepth: 5),
            fn () => $this->provider->alterTableStatement(maxDepth: 5),
            fn () => $this->provider->replaceStatement(maxDepth: 5),
            fn () => $this->provider->truncateStatement(maxDepth: 3),
        ];

        $index = ord($input[0] ?? "\0") % count($generators);
        return $generators[$index];
    }
}
