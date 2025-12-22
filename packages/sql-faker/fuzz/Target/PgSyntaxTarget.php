<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Faker\Generator;
use PDO;
use PDOException;
use SqlFaker\PostgreSqlProvider;

/**
 * Fuzz target for PostgreSQL SQL syntax validation.
 *
 * This target converts fuzzer input to RNG seed, generates SQL via grammar,
 * and validates it against PostgreSQL. Errors (not Exceptions) indicate bugs.
 */
final class PgSyntaxTarget
{
    private Generator $faker;

    private PostgreSqlProvider $provider;

    private PDO $pdo;

    private int $maxDepth;

    public function __construct(
        PDO $pdo,
        int $maxDepth = 8
    ) {
        $this->pdo = $pdo;
        $this->maxDepth = $maxDepth;

        $this->faker = Factory::create();
        $this->provider = new PostgreSqlProvider($this->faker);

        // PostgreSQL PDO::prepare() does not validate syntax (deferred preparation).
        // We use exec() with savepoints instead, which requires an open transaction.
        $this->pdo->exec('BEGIN');
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

    private function isBracketIndirectionSyntaxError(string $sql, string $message): bool
    {
        if (!str_contains($sql, '[')) {
            return false;
        }

        // PostgreSQL format: 'syntax error at or near "TOKEN"'
        if (preg_match('/at or near "([^"]+)"/', $message, $m) !== 1) {
            return false;
        }

        return preg_match('/\]\s+' . preg_quote($m[1], '/') . '\b/', $sql) === 1;
    }

    private function validateSyntax(string $sql, int $seed): void
    {
        if ($sql === '') {
            return;
        }

        try {
            $this->pdo->exec('SAVEPOINT fuzz_check');
            $this->pdo->exec($sql);
            $this->pdo->exec('RELEASE SAVEPOINT fuzz_check');
        } catch (PDOException $e) {
            try {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT fuzz_check');
            } catch (PDOException) {
                // If rollback fails, restart transaction
                try {
                    $this->pdo->exec('ROLLBACK');
                } catch (PDOException) {
                }
                $this->pdo->exec('BEGIN');
            }

            $sqlState = is_string($e->errorInfo[0] ?? null) ? $e->errorInfo[0] : '';

            $acceptable = match ($sqlState) {
                // SQLSTATE 42704: Undefined object
                '42704' => true,
                // SQLSTATE 42P01: Undefined table
                '42P01' => true,
                // SQLSTATE 42703: Undefined column
                '42703' => true,
                // SQLSTATE 3F000: Invalid schema name
                '3F000' => true,
                // SQLSTATE 0A000: Feature not supported
                '0A000' => true,
                // SQLSTATE 42809: Wrong object type
                '42809' => true,
                // SQLSTATE 25001: Active sql transaction
                '25001' => true,
                // SQLSTATE 22023: Invalid parameter value
                '22023' => true,
                // SQLSTATE 26000: Invalid sql statement name
                '26000' => true,
                // SQLSTATE 2BP01: Dependent objects still exist
                '2BP01' => true,
                // SQLSTATE 42602: Invalid name
                '42602' => true,
                // SQLSTATE 42883: Undefined function
                '42883' => true,
                // SQLSTATE 42939: Reserved name
                '42939' => true,
                // SQLSTATE 42P07: Duplicate table
                '42P07' => true,
                // SQLSTATE 42P10: Invalid column reference
                '42P10' => true,
                // SQLSTATE 58P01: Undefined file
                '58P01' => true,
                // SQLSTATE 42P13: Invalid function definition
                '42P13' => true,
                // SQLSTATE 3D000: Invalid catalog name
                '3D000' => true,
                // SQLSTATE 42P03: Duplicate cursor
                '42P03' => true,
                // SQLSTATE 22P02: Invalid text representation
                '22P02' => true,
                // SQLSTATE 25P01: No active sql transaction
                '25P01' => true,
                // SQLSTATE 42601: Syntax error (bracket indirection)
                '42601' => $this->isBracketIndirectionSyntaxError($sql, $e->getMessage()),
                default => false,
            };

            if ($acceptable) {
                return;
            }

            throw new \Error(
                "Unexpected error in generated SQL\n" .
                "Seed: $seed\n" .
                "SQL: $sql\n" .
                "SQLSTATE: $sqlState\n" .
                "Error: {$e->getMessage()}"
            );
        }
    }
}
