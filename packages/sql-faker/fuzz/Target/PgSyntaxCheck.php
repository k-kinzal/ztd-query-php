<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use PDOException;

/**
 * Executes generated SQL against PostgreSQL and reports unexpected rejections.
 *
 * PostgreSQL defers preparation, so PDO::prepare() accepts syntax the server
 * would still refuse. The statement therefore has to be executed, and each run
 * is wrapped in a savepoint so a rejected statement does not poison the
 * surrounding transaction. A fuzz run has no schema, so the grammar
 * legitimately produces statements that reference missing objects; those
 * SQLSTATEs are tolerated. Any other rejection means the grammar emitted
 * something PostgreSQL cannot parse, which is a finding and surfaces as an
 * Error for php-fuzzer to record.
 */
final class PgSyntaxCheck
{
    /**
     * @param PDO $pdo Connection to the PostgreSQL instance under test, with a transaction already open
     * @param PgBracketIndirection $bracketIndirection Recognises the dialect's bracketed-indirection syntax error
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly PgBracketIndirection $bracketIndirection,
    ) {
    }

    /**
     * Verifies that PostgreSQL accepts the generated statement.
     *
     * @param string $sql Statement produced by the grammar
     * @param int $seed Seed that produced the statement, so a finding can be replayed
     *
     * @throws Error When PostgreSQL rejects the statement for a reason the grammar should not produce
     */
    public function verify(string $sql, int $seed): void
    {
        try {
            $this->pdo->exec('SAVEPOINT fuzz_check');
            $this->pdo->exec($sql);
            $this->pdo->exec('RELEASE SAVEPOINT fuzz_check');
        } catch (PDOException $rejection) {
            $this->rollBack();

            $sqlState = is_string($rejection->errorInfo[0] ?? null) ? $rejection->errorInfo[0] : '';

            // A schema-less fuzz run cannot satisfy name lookups, so those SQLSTATEs are expected.
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
                '42601' => $this->bracketIndirection->explains($sql, $rejection->getMessage()),
                default => false,
            };

            if ($acceptable) {
                return;
            }

            throw new Error(
                "Unexpected error in generated SQL\n" .
                "Seed: $seed\n" .
                "SQL: $sql\n" .
                "SQLSTATE: $sqlState\n" .
                "Error: {$rejection->getMessage()}",
                0,
                $rejection
            );
        }
    }

    /**
     * Returns the connection to a state where the next statement can be checked.
     *
     * Rolling back to the savepoint is enough for an ordinary rejection. When
     * the savepoint itself is gone the whole transaction is unusable, so it is
     * discarded and a fresh one is opened instead.
     */
    public function rollBack(): void
    {
        try {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT fuzz_check');

            return;
        } catch (PDOException $savepointLoss) {
            // The savepoint did not survive the rejection, so the transaction is replaced below.
            fwrite(STDERR, "Savepoint rollback failed: {$savepointLoss->getMessage()}\n");
        }

        try {
            $this->pdo->exec('ROLLBACK');
        } catch (PDOException $rollbackFailure) {
            // No transaction was open, which the BEGIN below settles on its own.
            fwrite(STDERR, "Transaction rollback failed: {$rollbackFailure->getMessage()}\n");
        }

        $this->pdo->exec('BEGIN');
    }
}
