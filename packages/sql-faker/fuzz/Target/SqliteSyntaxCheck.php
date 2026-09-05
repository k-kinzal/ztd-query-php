<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use PDOException;

/**
 * Prepares generated SQL against SQLite and reports unexpected rejections.
 *
 * SQLite validates syntax when a statement is prepared, so a statement that
 * survives PDO::prepare() is syntactically accepted. SQLite reports its
 * failures as message text rather than as distinct error codes, so the
 * tolerated cases — name lookups a schema-less fuzz run cannot satisfy, plus a
 * handful of documented restrictions — are matched on the message. Any other
 * rejection means the grammar emitted something SQLite cannot parse, which is a
 * finding and surfaces as an Error for php-fuzzer to record.
 */
final class SqliteSyntaxCheck
{
    /**
     * @param PDO $pdo Connection to the in-memory SQLite database under test
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Verifies that SQLite parses the generated statement.
     *
     * @param string $sql Statement produced by the grammar
     * @param int $seed Seed that produced the statement, so a finding can be replayed
     *
     * @throws Error When SQLite rejects the statement for a reason the grammar should not produce
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
                    "Seed: $seed\n" .
                    "SQL: $sql"
                );
            }
        } catch (PDOException $rejection) {
            // A schema-less fuzz run cannot satisfy name lookups, so those messages are expected.
            $message = $rejection->getMessage();

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

            throw new Error(
                "Unexpected error in generated SQL\n" .
                "Seed: $seed\n" .
                "SQL: $sql\n" .
                "Error: $message",
                0,
                $rejection
            );
        }
    }
}
