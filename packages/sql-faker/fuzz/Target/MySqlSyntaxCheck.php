<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use PDOException;

/**
 * Prepares generated SQL against MySQL and reports unexpected rejections.
 *
 * MySQL validates syntax when a statement is prepared, so a statement that
 * survives PDO::prepare() is syntactically accepted by the server under test.
 * A fuzz run has no schema, so the grammar legitimately produces statements
 * that reference missing columns, databases or engines; those server errors are
 * tolerated. Any other rejection means the grammar emitted something MySQL
 * cannot parse, which is a finding and surfaces as an Error for php-fuzzer to
 * record.
 */
final class MySqlSyntaxCheck
{
    /**
     * @param PDO $pdo Connection to the MySQL instance under test
     * @param string $grammarVersion Grammar version that produced the statement
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $grammarVersion,
    ) {
    }

    /**
     * Verifies that MySQL parses the generated statement.
     *
     * @param string $sql Statement produced by the grammar
     * @param int $seed Seed that produced the statement, so a finding can be replayed
     *
     * @throws Error When MySQL rejects the statement for a reason the grammar should not produce
     */
    public function verify(string $sql, int $seed): void
    {
        if ($sql === '') {
            return;
        }

        try {
            $statement = $this->pdo->prepare($sql);
            if ($statement === false) {
                throw new Error(
                    "PDO::prepare returned false\n" .
                    "Grammar: {$this->grammarVersion}\n" .
                    "Seed: $seed\n" .
                    "SQL: $sql"
                );
            }
        } catch (PDOException $rejection) {
            // A schema-less fuzz run cannot satisfy name lookups, so those codes are expected.
            $errorCode = $rejection->errorInfo[1] ?? 0;

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
                // SQLSTATE[42000]: Error 3942 identifies an empty table value constructor
                3942 => false,
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

            throw new Error(
                "Unexpected error in generated SQL\n" .
                "Grammar: {$this->grammarVersion}\n" .
                "Seed: $seed\n" .
                "SQL: $sql\n" .
                'SQLSTATE: ' . (is_scalar($rejection->errorInfo[0] ?? null) ? (string) $rejection->errorInfo[0] : 'unknown') . "\n" .
                'Error Code: ' . (is_scalar($rejection->errorInfo[1] ?? null) ? (string) $rejection->errorInfo[1] : 'unknown') . "\n" .
                "Error: {$rejection->getMessage()}",
                0,
                $rejection
            );
        }
    }
}
