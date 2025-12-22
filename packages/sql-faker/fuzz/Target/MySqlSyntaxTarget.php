<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Faker\Generator;
use PDO;
use PDOException;
use SqlFaker\MySqlProvider;

/**
 * Fuzz target for MySQL SQL syntax validation.
 *
 * This target converts fuzzer input to RNG seed, generates SQL via grammar,
 * and validates it against MySQL. Errors (not Exceptions) indicate bugs.
 */
final class MySqlSyntaxTarget
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
                    "Grammar: {$this->grammarVersion}\n" .
                    "Seed: $seed\n" .
                    "SQL: $sql"
                );
            }
        } catch (PDOException $e) {
            $errorCode = $e->errorInfo[1] ?? 0;

            $acceptable = match ($errorCode) {
                // SQLSTATE[42S22]: Column not found: 1054
                1054 => true,
                // SQLSTATE[3D000]: Invalid catalog name: 1046
                1046 => true,
                // SQLSTATE[HY000]: General error: 1527 It is not allowed to specify STORAGE ENGINE more than once
                1527 => true,
                // SQLSTATE[HY000]: General error: 1273 Unknown collation
                1273 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 1327 Undeclared variable
                1327 => true,
                // SQLSTATE[SR006]: 3708 Missing mandatory attribute NAME
                3708 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 1407 Bad SQLSTATE
                1407 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 1049 Unknown database
                1049 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 1319 Undefined CONDITION
                1319 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 1305 PROCEDURE does not exist
                1305 => true,
                // SQLSTATE[HY000]: General error: 1096 No tables used
                1096 => true,
                // SQLSTATE[HY000]: General error: 1791 Unknown EXPLAIN format name
                1791 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 1286 Unknown storage engine
                1286 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 1235 Feature not supported
                1235 => true,
                // SQLSTATE[22003]: Numeric value out of range: 1690 SRID out of range
                1690 => true,
                // SQLSTATE[HY000]: General error: 3652 Invalid cpu id
                3652 => true,
                // SQLSTATE[SR006]: 3709 Multiple definitions of attribute NAME
                3709 => true,
                // SQLSTATE[HY000]: General error: 1525 Incorrect nth factor value
                1525 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 3942 VALUES clause must have at least one column
                3942 => true,
                // SQLSTATE[42S02]: Base table or view not found: 1051
                1051 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 3980 Invalid json attribute
                3980 => true,
                // SQLSTATE[HY000]: General error: 1193 Unknown system variable
                1193 => true,
                // SQLSTATE[HY000]: General error: 1277 Incorrect parameter for START REPLICA UNTIL
                1277 => true,
                // SQLSTATE[42000]: Syntax error or access violation: 1641 Duplicate condition information item
                1641 => true,
                default => false,
            };

            if ($acceptable) {
                return;
            }

            throw new \Error(
                "Unexpected error in generated SQL\n" .
                "Grammar: {$this->grammarVersion}\n" .
                "Seed: $seed\n" .
                "SQL: $sql\n" .
                "SQLSTATE: " . (is_scalar($e->errorInfo[0] ?? null) ? (string) $e->errorInfo[0] : 'unknown') . "\n" .
                "Error Code: " . (is_scalar($e->errorInfo[1] ?? null) ? (string) $e->errorInfo[1] : 'unknown') . "\n" .
                "Error: {$e->getMessage()}"
            );
        }
    }
}
