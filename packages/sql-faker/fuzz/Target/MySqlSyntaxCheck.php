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
 * parse_tree_partitions.cc compares subpartition counts after building partition metadata;
 * only its specific diagnostic is accepted when wrapped by error 1064.
 * parse_tree_helpers.cc validates resource-group priority using runtime platform bounds.
 * sql_yacc.yy checks fulltext parser registration and heartbeat ranges; item_create.cc checks CAST precision.
 * parse_tree_nodes.cc and sql_lex.cc reject CUBE and QUALIFY when the required engine or optimizer is unavailable.
 * sql_parse.cc checks ON UPDATE types and recursive references; sql_resolver.cc checks row widths.
 * sql_yacc.yy checks native function-name collisions and resolves cursor and loop labels; parse_tree_nodes.cc compares collations and charsets.
 * sql_update.cc checks updatability; sp_head.h checks trigger result sets; table.cc validates generated expressions.
 * item_func.cc checks stored-function argument names; item_json_func.cc resolves JSON paths and cast types.
 * window.cc resolves named windows and item_sum.cc checks their expression context.
 * sql_parse.cc checks default-value types; sql_view.cc checks view column counts.
 * item.cc and item_func.cc validate resolved row widths and bitwise operand types.
 * sql_partition.cc and table.cc validate expression functions after itemization.
 * sql_yacc.yy resolves duplicate routine names; user-name lengths are checked against USERNAME_CHAR_LENGTH.
 * item_timefunc.cc checks resolved AT TIME ZONE operand types.
 * create_field.cc checks defaults against resolved column types and SQL mode; table.cc rejects disallowed default-expression functions.
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
                1193, 1277, 1641, 1800, 1801, 1066, 1115, 3714, 6006, 3573, 3568, 3569, 1302, 1391, 3763, 1060, 1492, 3654, 1109,
                1128, 1426, 1624, 6033, 6037, 1294, 3577, 1585, 1253, 1324, 1136, 1308,
                1288, 1310, 1415, 1584, 1630, 3102, 3143, 3579, 3593, 3772, 4032, 4101,
                1353, 1067, 1111, 3769, 1564, 1332, 1241, 1330, 1470, 1101, 3770], true);

            if ($errorCode === 1221 && (str_ends_with($rejection->getMessage(), 'Incorrect usage of spatial/fulltext/hash index and explicit index order')
                || str_ends_with($rejection->getMessage(), 'Incorrect usage of SRID and non-geometry column'))) {
                return;
            }
            if ($errorCode === 1064 && preg_match('/\ASHOW\s+PARSE_TREE\b/i', $sql) === 1
                && str_contains($rejection->getMessage(), "near 'PARSE_TREE ")) {
                return;
            }
            $detail = $rejection->errorInfo[2] ?? null;
            if ($errorCode === 3998 && $detail === 'Cannot cast value to TIMESTAMP WITH TIME ZONE.') {
                return;
            }
            if ($errorCode === 1210 && in_array($detail, ['Incorrect arguments to >>', 'Incorrect arguments to <<', 'Incorrect arguments to &', 'Incorrect arguments to |', 'Incorrect arguments to ^'], true)) {
                return;
            }
            if ($errorCode === 1064 && is_string($detail) && (str_starts_with($detail, 'Constant, random or timezone-dependent expressions in (sub)partitioning function are not allowed near ')
                || str_starts_with($detail, 'Wrong number of subpartitions defined, mismatch with previous setting near '))) {
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
