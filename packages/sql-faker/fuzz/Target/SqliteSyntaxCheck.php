<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use PDO;
use PDOException;

/**
 * Prepares generated SQL against SQLite and reports unexpected rejections.
 *
 * Every input starts a fresh session of the same fixed SQLite engine because
 * some PRAGMAs take effect during preparation. SQLite validates syntax when a
 * statement is prepared, so a statement that survives PDO::prepare() is accepted.
 * Preparation also checks semantic restrictions: the grammar admits NULLS FIRST
 * and NULLS LAST in index column lists, then sqlite3HasExplicitNulls rejects them.
 * SQLite reports its
 * failures as message text rather than as distinct error codes, so the
 * tolerated cases — name lookups a schema-less fuzz run cannot satisfy, plus a
 * handful of documented restrictions — are matched on the message. Any other
 * rejection is an unclassified finding and surfaces as a SyntaxFailure for
 * PHP-Fuzzer to record. build.c checks view parameters and duplicate column
 * names; resolve.c resolves operator functions and validates arity.
 * expr.c checks vector assignment dimensions before or after SELECT expansion.
 * build.c resolves local foreign-key columns and checks inline and table-level key widths.
 * Its makeColumnPartOfPrimaryKey rejects generated columns in primary keys.
 * window.c/windowFind resolves names against the current SELECT's window definitions.
 * attach.c/fixSelectCb checks cross-database trigger references.
 */
final class SqliteSyntaxCheck
{
    /**
     * Verifies that SQLite parses the generated statement.
     * build.c validates column defaults, generated columns and key shapes; select.c checks compound widths.
     * window.c resolves a named base window before checking whether its frame can be inherited.
     * resolve.c checks aggregate context and schema-expression restrictions; attach.c checks view references.
     *
     * @param string $sql Statement produced by the grammar
     * @param string $input Original fuzzer input, encoded as hex by the target
     *
     * @throws InfrastructureFailure When the database environment is unavailable
     * @throws SyntaxFailure When SQLite rejects the statement for a reason the grammar should not produce
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new SyntaxFailure('Statement generation returned an empty string.');
        }

        try {
            $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $statement = $pdo->prepare($sql);
            if ($statement === false) {
                throw new SyntaxFailure(
                    "PDO::prepare returned false\n" .
                    "Input (hex): {$input}\n" .
                    "SQL: $sql"
                );
            }
            return;
        } catch (PDOException $rejection) {

            $message = $rejection->getMessage();
            if (in_array($rejection->errorInfo[1] ?? 0, [7, 10, 11, 13, 14, 26], true)) {
                throw new InfrastructureFailure('SQLite verification environment failed.', 0, $rejection);
            }

            $acceptable = match (true) {
                str_contains($message, 'General error: 1 no such table:') => true,
                str_contains($message, 'General error: 1 unknown database') => true,
                str_contains($message, 'General error: 1 no such view:') => true,
                str_contains($message, 'temporary trigger may not have qualified name') => true,
                str_contains($message, 'ORDER BY may not be used with non-aggregate') => true,
                str_ends_with($message, 'General error: 1 HAVING clause on a non-aggregate query') => true,
                str_contains($message, 'General error: 1 no such index:') => true,
                str_contains($message, 'General error: 1 no tables specified') => true,
                str_contains($message, 'General error: 1 no such column:') => true,
                preg_match('/General error: 1 unknown column "[^\r\n]*" in foreign key definition\z/D', $message) === 1 => true,
                str_contains($message, 'all VALUES must have the same number of terms') => true,
                preg_match('/General error: 1 [0-9]+ columns assigned [0-9]+ values\z/D', $message) === 1 => true,
                str_contains($message, 'General error: 1 no such function:') => true,
                str_starts_with($message, 'SQLSTATE[HY000]: General error: 1 no such window: ') => true,
                preg_match('/General error: 1 SELECTs to the left and right of (?:UNION(?: ALL)?|INTERSECT|EXCEPT) do not have the same number of result columns\z/D', $message) === 1 => true,
                str_contains($message, 'General error: 1 no such trigger:') => true,
                str_contains($message, 'unable to identify the object to be reindexed') => true,
                str_contains($message, 'RAISE() may only be used within a trigger-program') => true,
                str_contains($message, 'General error: 1 row value misused') => true,
                str_contains($message, 'General error: 1 no such collation sequence:') => true,
                str_contains($message, 'DISTINCT is not supported for window functions') => true,
                preg_match('/General error: 1 wrong number of arguments to function (?:GLOB|MATCH)\(\)\z/D', $message) === 1 => true,
                str_contains($message, 'duplicate WITH table name:') => true,
                str_ends_with($message, 'General error: 1 unsupported use of NULLS FIRST') => true,
                str_ends_with($message, 'General error: 1 unsupported use of NULLS LAST') => true,
                str_contains($message, 'General error: 1 parameters are not allowed in views') => true,
                str_contains($message, 'General error: 1 duplicate column name:') => true,
                preg_match('/General error: 1 table "[^\r\n]*" has more than one primary key\z/D', $message) === 1 => true,
                str_ends_with($message, 'General error: 1 AUTOINCREMENT is only allowed on an INTEGER PRIMARY KEY') => true,
                str_ends_with($message, 'General error: 1 generated columns cannot be part of the PRIMARY KEY') => true,
                str_ends_with($message, 'General error: 1 must have at least one non-generated column') => true,
                preg_match('/General error: 1 default value of column \[[^\r\n]*\] is not constant\z/D', $message) === 1 => true,
                str_ends_with($message, 'General error: 1 number of columns in foreign key does not match the number of columns in the referenced table') => true,
                preg_match('/\\ASQLSTATE\\[HY000\\]: General error: 1 foreign key on [^\\r\\n]+ should reference only one column of table [^\\r\\n]+\\z/D', $message) === 1 => true,
                str_ends_with($message, 'General error: 1 expressions prohibited in PRIMARY KEY and UNIQUE constraints') => true,
                preg_match('/\ASQLSTATE\[HY000\]: General error: 1 cannot override (?:frame specification|PARTITION clause|ORDER BY clause) of window: /D', $message) === 1 => true,
                str_ends_with($message, 'General error: 1 conflicting ON CONFLICT clauses specified') => true,
                preg_match('/General error: 1 parameters prohibited in (?:CHECK constraints|index expressions|partial index WHERE clauses|generated columns)\z/D', $message) === 1 => true,
                preg_match('/General error: 1 (?:non-deterministic functions|the "\." operator) prohibited in (?:CHECK constraints|index expressions|partial index WHERE clauses|generated columns)\z/D', $message) === 1 => true,
                preg_match('/General error: 1 view [^\r\n]* cannot reference objects in database [^\r\n]*\z/D', $message) === 1 => true,
                preg_match('/General error: 1 trigger .* cannot reference objects in database /', $message) === 1 => true,
                default => false,
            };

            if ($acceptable) {
                return;
            }

            throw new SyntaxFailure(
                "Unexpected error in generated SQL\n" .
                "Input (hex): {$input}\n" .
                "SQL: $sql\n" .
                "Error: $message",
                0,
                $rejection
            );
        }
    }
}
