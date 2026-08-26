<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Faker\Generator;
use Fuzz\Robustness\Invariant\ShadowStoreConsistencyChecker;
use SqlFaker\MySqlProvider;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Shadow\ShadowStore;

/**
 * The robustness target.
 */
final class RobustnessTarget
{
    private Generator $faker;
    private MySqlProvider $provider;
    private MySqlRewriter $rewriter;
    private ShadowStore $shadowStore;
    private ShadowStoreConsistencyChecker $storeChecker;
    /**
     * Answers the generator this input should be fuzzed through.
     *
     * @param string $input Bytes the fuzzer handed over
     *
     * @return callable(): string The generator to draw a statement from
     */
    public function selectGenerator(string $input): callable
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
