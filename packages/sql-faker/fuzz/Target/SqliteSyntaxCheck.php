<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use PDOException;

/**
 * Prepares generated SQL on SQLite and reports unexpected rejections.
 *
 * SQLite validates syntax when a statement is prepared, and it reports failures as message
 * text rather than distinct codes. A fuzz run has no schema, so the messages the grammar
 * cannot avoid are listed below with the reason each one is tolerated. Every other rejection
 * is a finding: it surfaces as an Error for PHP-Fuzzer to record together with the input.
 */
final class SqliteSyntaxCheck
{
    /**
     * Prepare failures tolerated for any statement, as [message pattern, reason].
     *
     * Each of them is raised after the statement was parsed, while SQLite resolves names
     * that a schema-less run cannot provide or applies a documented restriction.
     *
     * @var list<array{string, string}>
     */
    public const IGNORED_MESSAGES = [
        ['/General error: 1 no such table:/', 'table names are generated, none exists.'],
        ['/General error: 1 no such view:/', 'view names are generated, none exists.'],
        ['/General error: 1 no such index:/', 'index names are generated, none exists.'],
        ['/General error: 1 no such column:/', 'column names are generated, none exists.'],
        ['/General error: 1 no such function:/', 'function names are generated, none exists.'],
        ['/General error: 1 no such trigger:/', 'trigger names are generated, none exists.'],
        ['/General error: 1 no such window: /', 'window names are generated identifiers.'],
        ['/General error: 1 no such collation sequence:/', 'collation names are generated identifiers.'],
        ['/General error: 1 unknown database/', 'database names are generated, none is attached.'],
        ['/General error: 1 no tables specified/', 'the resolver needs a table where the grammar allows none.'],
        ['/General error: 1 unknown column "[^\r\n]*" in foreign key definition$/', 'foreign key columns are resolved after parsing.'],
        ['/General error: 1 duplicate column name:/', 'generated column lists may repeat a name.'],
        ['/General error: 1 table "[^\r\n]*" has more than one primary key$/', 'generated constraints may repeat PRIMARY KEY.'],
        ['/General error: 1 AUTOINCREMENT is only allowed on an INTEGER PRIMARY KEY$/', 'column constraints are checked after parsing.'],
        ['/General error: 1 generated columns cannot be part of the PRIMARY KEY$/', 'column constraints are checked after parsing.'],
        ['/General error: 1 must have at least one non-generated column$/', 'column lists are checked after parsing.'],
        ['/General error: 1 default value of column \[[^\r\n]*\] is not constant$/', 'default expressions are checked after parsing.'],
        ['/General error: 1 number of columns in foreign key does not match the number of columns in the referenced table$/', 'foreign keys are resolved after parsing.'],
        ['/General error: 1 foreign key on [^\r\n]+ should reference only one column of table [^\r\n]+$/', 'foreign keys are resolved after parsing.'],
        ['/General error: 1 expressions prohibited in PRIMARY KEY and UNIQUE constraints$/', 'constraint expressions are checked after parsing.'],
        ['/General error: 1 conflicting ON CONFLICT clauses specified$/', 'generated constraints may combine ON CONFLICT clauses.'],
        ['/General error: 1 parameters prohibited in (?:CHECK constraints|index expressions|partial index WHERE clauses|generated columns)$/', 'parameter placement is checked after parsing.'],
        ['/General error: 1 (?:non-deterministic functions|the "\." operator) prohibited in (?:CHECK constraints|index expressions|partial index WHERE clauses|generated columns)$/', 'expression restrictions are checked after parsing.'],
        ['/General error: 1 parameters are not allowed in views$/', 'view bodies are checked after parsing.'],
        ['/General error: 1 view [^\r\n]* cannot reference objects in database [^\r\n]*$/', 'cross-database references are checked after parsing.'],
        ['/General error: 1 trigger .* cannot reference objects in database /', 'cross-database references are checked after parsing.'],
        ['/temporary trigger may not have qualified name/', 'trigger names are checked after parsing.'],
        ['/unable to identify the object to be reindexed/', 'REINDEX names are generated, none exists.'],
        ['/RAISE\(\) may only be used within a trigger-program/', 'RAISE placement is checked after parsing.'],
        ['/ORDER BY may not be used with non-aggregate/', 'aggregate ORDER BY is checked after parsing.'],
        ['/General error: 1 HAVING clause on a non-aggregate query$/', 'HAVING placement is checked after parsing.'],
        ['/General error: 1 row value misused$/', 'row value contexts are checked after parsing.'],
        ['/all VALUES must have the same number of terms/', 'row widths are checked after parsing.'],
        ['/General error: 1 [0-9]+ columns assigned [0-9]+ values$/', 'row widths are checked after parsing.'],
        ['/General error: 1 SELECTs to the left and right of (?:UNION(?: ALL)?|INTERSECT|EXCEPT) do not have the same number of result columns$/', 'compound widths are checked after parsing.'],
        ['/DISTINCT is not supported for window functions/', 'window function options are checked after parsing.'],
        ['/General error: 1 wrong number of arguments to function (?:GLOB|MATCH)\(\)$/', 'built-in function arity is checked after parsing.'],
        ['/duplicate WITH table name:/', 'generated CTE lists may repeat a name.'],
        ['/General error: 1 unsupported use of NULLS (?:FIRST|LAST)$/', 'NULLS ordering placement is checked after parsing.'],
        ['/General error: 1 cannot override (?:frame specification|PARTITION clause|ORDER BY clause) of window: /', 'window inheritance is checked after parsing.'],
    ];

    /**
     * Extended result codes that mean the engine failed rather than that the statement was rejected.
     *
     * @var list<int>
     */
    public const ENGINE_ERRORS = [7, 10, 11, 13, 14, 26];

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
     * @param string $input Fuzzer input that produced the statement, so a finding can be replayed
     *
     * @throws Error When SQLite rejects the statement for a reason the grammar should not produce
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\nInput (hex): " . bin2hex($input));
        }
        try {
            $statement = $this->pdo->prepare($sql);
            if ($statement === false) {
                throw new Error("PDO::prepare returned false\nInput (hex): " . bin2hex($input) . "\nSQL: $sql");
            }
        } catch (PDOException $rejection) {
            $code = $rejection->errorInfo[1] ?? 0;
            if (in_array($code, self::ENGINE_ERRORS, true)) {
                fwrite(STDERR, "SQLite engine failed: {$rejection->getMessage()}\n");
                exit(2);
            }
            $message = $rejection->getMessage();
            foreach (self::IGNORED_MESSAGES as [$pattern, $reason]) {
                if (preg_match($pattern, $message) === 1) {
                    return;
                }
            }
            throw new Error(
                "Unexpected error in generated SQL\n" .
                'Input (hex): ' . bin2hex($input) . "\n" .
                "SQL: $sql\n" .
                "Error: $message",
                0,
                $rejection
            );
        }
    }
}
