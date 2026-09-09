<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use PDO;
use PDOException;

/**
 * Prepares generated SQL against MySQL and reports unexpected rejections.
 *
 * SQL PREPARE reaches the server directly, avoiding PDO's emulation fallback.
 * Unsupported preparation and known schema or semantic rejections are inconclusive.
 * In particular, ALGORITHM and LOCK accept identifiers in the grammar, then
 * semantic actions reject unknown values with errors 1800 and 1801.
 * Other syntax errors and unclassified rejections are findings. Duplicate aliases,
 * unknown charsets, SRS restrictions, unresolved locks and recursive-CTE shape
 * checks are semantic rejections. SHOW PARSE_TREE requires WITH_SHOW_PARSE_TREE
 * in sql_yacc.yy, which is absent in the pinned release build.
 * Key-prefix lengths and allowed generated-column functions are semantic constraints.
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
     * @param string $input Original fuzzer input, encoded as hex by the target
     *
     * @throws InfrastructureFailure When the database environment is unavailable
     * @throws SyntaxFailure When MySQL rejects the statement for a reason the grammar should not produce
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new SyntaxFailure('Statement generation returned an empty string.');
        }

        try {
            $quoted = $this->pdo->quote($sql);
            if ($quoted === false) {
                throw new InfrastructureFailure('Cannot quote generated SQL for server PREPARE.');
            }
            $this->pdo->exec('SET @sql_faker_input = ' . $quoted);
            $this->pdo->exec('PREPARE sql_faker_check FROM @sql_faker_input');
            $this->pdo->exec('DEALLOCATE PREPARE sql_faker_check');
            return;
        } catch (PDOException $rejection) {

            $errorCode = $rejection->errorInfo[1] ?? 0;
            if (in_array($errorCode, [2002, 2006, 2013, 1040], true)) {
                throw new InfrastructureFailure('MySQL verification connection failed.', 0, $rejection);
            }
            if ($errorCode === 1295) {
                return;
            }

            $acceptable = in_array($errorCode, [1054, 1046, 1527, 1273, 1327, 3708, 1407, 1049,
                1319, 1305, 1096, 1791, 1286, 1235, 1690, 3652, 3709, 1525, 1051, 3980,
                1193, 1277, 1641, 1800, 1801, 1066, 1115, 3714, 6006, 3573, 3568, 1302, 1391, 3763, 1060, 1492], true);

            if ($errorCode === 1221 && (str_ends_with($rejection->getMessage(), 'Incorrect usage of spatial/fulltext/hash index and explicit index order')
                || str_ends_with($rejection->getMessage(), 'Incorrect usage of SRID and non-geometry column'))) {
                return;
            }
            if ($errorCode === 1064 && preg_match('/\ASHOW\s+PARSE_TREE\b/i', $sql) === 1
                && str_contains($rejection->getMessage(), "near 'PARSE_TREE ")) {
                return;
            }
            $detail = $rejection->errorInfo[2] ?? null;
            if ($errorCode === 1064 && is_string($detail) && str_starts_with($detail, 'Constant, random or timezone-dependent expressions in (sub)partitioning function are not allowed near ')) {
                return;
            }

            if ($acceptable) {
                return;
            }

            throw new SyntaxFailure(
                "Unexpected error in generated SQL\n" .
                "Grammar: {$this->grammarVersion}\n" .
                "Input (hex): {$input}\n" .
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
