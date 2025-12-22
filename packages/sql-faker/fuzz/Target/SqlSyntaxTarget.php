<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Faker\Generator;
use PDO;
use PDOException;
use SqlFaker\MySqlProvider;

/**
 * Fuzz target for SQL syntax validation.
 *
 * This target converts fuzzer input to RNG seed, generates SQL via grammar,
 * and validates it against MySQL. Errors (not Exceptions) indicate bugs.
 */
final class SqlSyntaxTarget
{
    private Generator $faker;

    private MySqlProvider $provider;

    private PDO $pdo;

    private string $grammarVersion;

    private int $maxDepth;

    public function __construct(
        PDO $pdo,
        string $grammarVersion,
        int $maxDepth = 8
    ) {
        $this->pdo = $pdo;
        $this->grammarVersion = $grammarVersion;
        $this->maxDepth = $maxDepth;

        $this->faker = Factory::create();
        $this->provider = new MySqlProvider($this->faker, $grammarVersion);
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
        // Convert input to deterministic seed
        $seed = $this->inputToSeed($input);
        $this->faker->seed($seed);

        // Generate SQL (provider reuses grammar, only RNG changes via seed)
        $sql = $this->provider->sql(maxDepth: $this->maxDepth);

        // Validate syntax
        $this->validateSyntax($sql, $seed);
    }

    private function inputToSeed(string $input): int
    {
        // Use crc32 for speed; hash for better distribution
        if (strlen($input) < 4) {
            $input = str_pad($input, 4, "\0");
        }

        return crc32($input);
    }

    private function validateSyntax(string $sql, int $seed): void
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                throw new \Error(
                    "PDO::prepare returned false\n" .
                    "Grammar: {$this->grammarVersion}\n" .
                    "Seed: $seed\n" .
                    "SQL: $sql"
                );
            }
        } catch (PDOException $e) {
            $errorCode = $e->errorInfo[1] ?? 0;

            // MySQL error code 1064 = Pure syntax error
            // Other 42xxx errors (unknown table/column/procedure) are semantic, not syntax errors
            if ($errorCode === 1064) {
                throw new \Error(
                    "Syntax error in generated SQL\n" .
                    "Grammar: {$this->grammarVersion}\n" .
                    "Seed: $seed\n" .
                    "SQL: $sql\n" .
                    "Error: {$e->getMessage()}"
                );
            }

            // Other errors (unknown table, unknown column, etc.) are acceptable for syntax validation
        }
    }
}
