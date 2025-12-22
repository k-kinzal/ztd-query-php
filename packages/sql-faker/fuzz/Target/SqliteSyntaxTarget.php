<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Faker\Generator;
use PDO;
use PDOException;
use SqlFaker\SqliteProvider;

/**
 * Fuzz target for SQLite SQL syntax validation.
 *
 * This target converts fuzzer input to RNG seed, generates SQL via grammar,
 * and validates it against SQLite. Errors (not Exceptions) indicate bugs.
 */
final class SqliteSyntaxTarget
{
    private Generator $faker;

    private SqliteProvider $provider;

    private PDO $pdo;

    private int $maxDepth;

    public function __construct(
        PDO $pdo,
        int $maxDepth = 8
    ) {
        $this->pdo = $pdo;
        $this->maxDepth = $maxDepth;

        $this->faker = Factory::create();
        $this->provider = new SqliteProvider($this->faker);
    }

    /**
     * Fuzz target callable.
     *
     * @param string $input Raw fuzzer input (mutated bytes)
     *
     * @throws \Error On syntax validation failure (caught by fuzzer)
     */
    public function __invoke(string $input): void
    {
        $seed = $this->inputToSeed($input);
        $this->faker->seed($seed);

        $sql = $this->provider->sql(maxDepth: $this->maxDepth);

        $this->validateSyntax($sql, $seed);
    }

    private function inputToSeed(string $input): int
    {
        if (strlen($input) < 4) {
            $input = str_pad($input, 4, "\0");
        }

        return crc32($input);
    }

    private function validateSyntax(string $sql, int $seed): void
    {
        if ($sql === '') {
            return;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                throw new \Error(
                    "PDO::prepare returned false\n" .
                    "Seed: $seed\n" .
                    "SQL: $sql"
                );
            }
        } catch (PDOException $e) {
            $message = $e->getMessage();

            $acceptable = match (true) {
                str_contains($message, 'General error: 1 no such table:') => true,
                str_contains($message, 'General error: 1 incomplete input') => true,
                str_contains($message, 'General error: 1 unknown database') => true,
                str_contains($message, 'General error: 1 no such view:') => true,
                str_contains($message, 'temporary trigger may not have qualified name') => true,
                str_contains($message, 'ORDER BY may not be used with non-aggregate') => true,
                str_contains($message, 'General error: 1 no such index:') => true,
                str_contains($message, 'General error: 1 no tables specified') => true,
                str_contains($message, 'General error: 1 no such column:') => true,
                str_contains($message, 'all VALUES must have the same number of terms') => true,
                str_contains($message, 'General error: 1 no such function:') => true,
                str_contains($message, 'SELECTs to the left and right of UNION do not have the same number of result columns') => true,
                str_contains($message, 'General error: 1 no such trigger:') => true,
                str_contains($message, 'unable to identify the object to be reindexed') => true,
                str_contains($message, 'RAISE() may only be used within a trigger-program') => true,
                str_contains($message, 'General error: 1 row value misused') => true,
                str_contains($message, 'General error: 1 no such collation sequence:') => true,
                str_contains($message, 'DISTINCT is not supported for window functions') => true,
                str_contains($message, 'wrong number of arguments to function GLOB()') => true,
                str_contains($message, 'duplicate WITH table name:') => true,
                default => false,
            };

            if ($acceptable) {
                return;
            }

            throw new \Error(
                "Unexpected error in generated SQL\n" .
                "Seed: $seed\n" .
                "SQL: $sql\n" .
                "Error: $message"
            );
        }
    }
}
