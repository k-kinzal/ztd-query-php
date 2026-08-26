<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Faker\Generator;
use SqlFaker\MySqlProvider;

/**
 * The rewrite target.
 */
final class RewriteTarget
{
    private Generator $faker;
    private MySqlProvider $provider;
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
            fn (): string => "UPDATE users SET status = {$this->provider->quotedHexLiteral()} WHERE id = 1",
            fn (): string => "UPDATE orders SET created_at = created_at + INTERVAL {$this->provider->integerLiteral(1, 30)} DAY WHERE id = 1",
            fn (): string => 'UPDATE users SET status = 0 WHERE CASE WHEN id > 1 THEN 1 ELSE 0 END = 1',
            fn (): string => 'DELETE FROM users WHERE CASE WHEN id > 1 THEN 1 ELSE 0 END = 1',
            fn (): string => "CREATE TABLE child (id INT, parent_id INT, {$this->provider->foreignKeyConstraint()})",
            fn (): string => $this->provider->updateJoinDerivedStatement(),
            fn (): string => $this->provider->insertSelectCompoundStatement(),
            fn (): string => $this->provider->insertRowAliasUpsertStatement(),
            fn (): string => $this->provider->insertFunctionUpsertStatement(),
            fn (): string => $this->provider->temporaryTableStatement(),
            fn (): string => $this->provider->viewStatement(),
            fn (): string => $this->provider->generatedColumnStatement(),
            fn (): string => $this->provider->foreignKeyCascadeStatement(),
            fn (): string => $this->provider->partitionSelectStatement(),
            fn (): string => $this->provider->loadDataStatement(maxDepth: 8),
            fn (): string => $this->provider->fullTextSearchStatement(),
        ];

        $index = ord($input[0] ?? "\0") % count($generators);
        return $generators[$index];
    }
}
