<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use PDOException;

/**
 * Prepares generated SQL on the MySQL server under test and reports unexpected rejections.
 *
 * The statement goes through the server's own PREPARE, so PDO never interprets placeholders
 * in the generated text. A fuzz run has no schema, so the server errors the grammar cannot
 * avoid are listed below with the reason each one is tolerated. Every other rejection is a
 * finding: it surfaces as an Error for PHP-Fuzzer to record together with the input.
 */
final class MySqlSyntaxCheck
{
    /**
     * Server errors tolerated for any statement, keyed by MySQL error code.
     *
     * Each of them is raised after the statement was parsed, while the server resolves names
     * that a schema-less run cannot provide or checks semantics the grammar does not encode.
     *
     * @var array<int, string>
     */
    public const IGNORED_CODES = [
        1046 => 'ER_NO_DB_ERROR: no default database is selected during the run.',
        1049 => 'ER_BAD_DB_ERROR: database names are generated, none exists.',
        1051 => 'ER_BAD_TABLE_ERROR: table names are generated, none exists.',
        1054 => 'ER_BAD_FIELD_ERROR: column names are generated, none exists.',
        1060 => 'ER_DUP_FIELDNAME: generated column lists may repeat a name.',
        1066 => 'ER_NONUNIQ_TABLE: generated table references may repeat an alias.',
        1067 => 'ER_INVALID_DEFAULT: the default value is checked against the column type after parsing.',
        1096 => 'ER_NO_TABLES_USED: the analyzer needs a table where the grammar allows none.',
        1101 => 'ER_BLOB_CANT_HAVE_DEFAULT: default values are checked against the column type after parsing.',
        1109 => 'ER_UNKNOWN_TABLE: table names are generated, none exists.',
        1111 => 'ER_INVALID_GROUP_FUNC_USE: aggregate placement is checked during analysis.',
        1115 => 'ER_UNKNOWN_CHARACTER_SET: character set names are generated identifiers.',
        1128 => 'ER_FUNCTION_NOT_DEFINED: loadable function names are generated, none is installed.',
        1136 => 'ER_WRONG_VALUE_COUNT_ON_ROW: row width is checked against the column list after parsing.',
        1193 => 'ER_UNKNOWN_SYSTEM_VARIABLE: variable names are generated identifiers.',
        1235 => 'ER_NOT_SUPPORTED_YET: the grammar still lists syntax the server rejects after parsing.',
        1241 => 'ER_OPERAND_COLUMNS: row value width is checked during analysis.',
        1253 => 'ER_COLLATION_CHARSET_MISMATCH: generated collations and character sets are combined freely.',
        1267 => 'ER_CANT_AGGREGATE_2COLLATIONS: collation mixing is checked during analysis.',
        1273 => 'ER_UNKNOWN_COLLATION: collation names are generated identifiers.',
        1277 => 'ER_BAD_REPLICA_UNTIL_COND: START REPLICA UNTIL options are validated after parsing.',
        1286 => 'ER_UNKNOWN_STORAGE_ENGINE: engine names are generated identifiers.',
        1288 => 'ER_NON_UPDATABLE_TABLE: updatability is checked during analysis.',
        1294 => 'ER_INVALID_ON_UPDATE: ON UPDATE is checked against the column type after parsing.',
        1295 => 'ER_UNSUPPORTED_PS: the statement parsed but PREPARE refuses this statement kind.',
        1302 => 'ER_CONFLICTING_DECLARATIONS: conflicting column attributes are rejected after parsing.',
        1305 => 'ER_SP_DOES_NOT_EXIST: routine names are generated, none exists.',
        1308 => 'ER_SP_LILABEL_MISMATCH: routine labels are generated identifiers.',
        1310 => 'ER_SP_LABEL_MISMATCH: routine labels are generated identifiers.',
        1319 => 'ER_SP_COND_MISMATCH: condition names are generated identifiers.',
        1324 => 'ER_SP_CURSOR_MISMATCH: cursor names are generated identifiers.',
        1327 => 'ER_SP_UNDECLARED_VAR: routine variable names are generated identifiers.',
        1330 => 'ER_SP_DUP_PARAM: generated parameter lists may repeat a name.',
        1332 => 'ER_SP_DUP_COND: generated condition declarations may repeat a name.',
        1353 => 'ER_VIEW_WRONG_LIST: view column counts are checked after parsing.',
        1391 => 'ER_KEY_PART_0: key part lengths are checked after parsing.',
        1407 => 'ER_SP_BAD_SQLSTATE: SQLSTATE literals are generated strings.',
        1415 => 'ER_SP_NO_RETSET: result sets in routines are rejected during analysis.',
        1426 => 'ER_TOO_BIG_PRECISION: numeric precision is checked after parsing.',
        1470 => 'ER_WRONG_STRING_LENGTH: user and host names are generated strings.',
        1492 => 'ER_PARTITIONS_MUST_BE_DEFINED_ERROR: partition definitions are checked after parsing.',
        1525 => 'ER_WRONG_VALUE: literal values are checked against their context after parsing.',
        1527 => 'ER_WARN_OPTION_MORE_THAN_ONCE: repeated options are rejected after parsing.',
        1564 => 'ER_PARTITION_FUNCTION_IS_NOT_ALLOWED: partition expressions are checked after parsing.',
        1584 => 'ER_WRONG_PARAMETERS_TO_STORED_FCT: routine calls are resolved during analysis.',
        1585 => 'ER_NATIVE_FCT_NAME_COLLISION: generated routine names may collide with native functions.',
        1624 => 'ER_REPLICA_HEARTBEAT_VALUE_OUT_OF_RANGE: replication options are validated after parsing.',
        1630 => 'ER_FUNC_INEXISTENT_NAME_COLLISION: function names are generated, none exists.',
        1641 => 'ER_DUP_SIGNAL_SET: generated SIGNAL items may repeat a name.',
        1690 => 'ER_DATA_OUT_OF_RANGE: literal ranges are checked after parsing.',
        1791 => 'ER_UNKNOWN_EXPLAIN_FORMAT: EXPLAIN format names are generated identifiers.',
        1800 => 'ER_UNKNOWN_ALTER_ALGORITHM: ALGORITHM names are generated identifiers.',
        1801 => 'ER_UNKNOWN_ALTER_LOCK: LOCK names are generated identifiers.',
        3102 => 'ER_GENERATED_COLUMN_FUNCTION_IS_NOT_ALLOWED: generated column expressions are checked after parsing.',
        3143 => 'ER_INVALID_JSON_PATH: JSON paths are generated strings.',
        3146 => 'ER_INVALID_TYPE_FOR_JSON: JSON arguments are checked during analysis.',
        3568 => 'ER_UNRESOLVED_TABLE_LOCK: locking clauses are resolved during analysis.',
        3569 => 'ER_DUPLICATE_TABLE_LOCK: locking clauses are resolved during analysis.',
        3573 => 'ER_CTE_RECURSIVE_REQUIRES_UNION: recursive CTE shape is checked after parsing.',
        3577 => 'ER_CTE_RECURSIVE_REQUIRES_SINGLE_REFERENCE: recursive CTE shape is checked after parsing.',
        3579 => 'ER_WINDOW_NO_SUCH_WINDOW: window names are generated identifiers.',
        3593 => 'ER_WINDOW_INVALID_WINDOW_FUNC_USE: window function placement is checked during analysis.',
        3652 => 'ER_INVALID_VCPU_ID: resource group values are checked after parsing.',
        3654 => 'ER_INVALID_THREAD_PRIORITY: resource group values are checked after parsing.',
        3708 => 'ER_RESOURCE_GROUP_MISSING_MANDATORY_ATTRIBUTE: resource group attributes are checked after parsing.',
        3709 => 'ER_RESOURCE_GROUP_MULTIPLE_ATTRIBUTE_DEFINITION: resource group attributes are checked after parsing.',
        3714 => 'ER_CANT_MODIFY_SRID_0: SRID values are checked after parsing.',
        3763 => 'ER_GENERATED_COLUMN_NAMED_FUNCTION_IS_NOT_ALLOWED: generated column expressions are checked after parsing.',
        3769 => 'ER_DEFAULT_VAL_GENERATED_FUNCTION_IS_NOT_ALLOWED: default expressions are checked after parsing.',
        3770 => 'ER_DEFAULT_VAL_GENERATED_NAMED_FUNCTION_IS_NOT_ALLOWED: default expressions are checked after parsing.',
        3772 => 'ER_DEFAULT_VAL_GENERATED_VARIABLES: default expressions are checked after parsing.',
        3980 => 'ER_INVALID_JSON_ATTRIBUTE: JSON attribute strings are generated text.',
        3995 => 'ER_CHARACTER_SET_MISMATCH: character set combinations are checked during analysis.',
        4032 => 'ER_INVALID_CAST: cast targets are checked during analysis.',
        4101 => 'ER_NATIVE_FCT_NAME_COLLISION_WITH_IF_NOT_EXISTS: generated routine names may collide with native functions.',
        6006 => 'ER_EXPLAIN_INTO_IMPLICIT_FORMAT_NOT_SUPPORTED: EXPLAIN INTO options are checked after parsing.',
        6033 => 'ER_CANNOT_EXECUTE_IN_PRIMARY: the statement parsed but this server role refuses it.',
        6037 => 'ER_SUPPORTED_ONLY_WITH_HYPERGRAPH: the statement parsed but needs the hypergraph optimizer.',
    ];

    /**
     * Server errors tolerated only with a specific message, as [code, message pattern, reason].
     *
     * These codes also cover rejections the grammar must not produce, so the message decides.
     *
     * @var list<array{int, string, string}>
     */
    public const IGNORED_MESSAGES = [
        [1064, '/ near \'PARSE_TREE /', 'ER_PARSE_ERROR on SHOW PARSE_TREE: only debug builds compile that statement.'],
        [1064, '/^(?:Constant, random or timezone-dependent expressions in \(sub\)partitioning function are not allowed|Wrong number of subpartitions defined, mismatch with previous setting) near /', 'ER_PARSE_ERROR raised by partition analysis after the statement parsed.'],
        [1210, '/^Incorrect arguments to (?:>>|<<|&|\||\^|<|<=|>|>=|like|DIV|%|\+|-|\*|\/)$/', 'ER_WRONG_ARGUMENTS: operator arguments are checked during analysis.'],
        [1221, '/^Incorrect usage of (?:spatial\/fulltext\/hash index and explicit index order|SRID and non-geometry column)$/', 'ER_WRONG_USAGE: index and column attribute combinations are checked after parsing.'],
        [1351, '/^View\'s SELECT contains a variable or parameter$/', 'ER_VIEW_SELECT_VARIABLE: view bodies are checked after parsing.'],
        [1367, '/^Illegal non geometric \'.*\' value found during parsing$/', 'ER_ILLEGAL_VALUE_FOR_TYPE: geometry literals are generated strings.'],
        [3580, '/^There is a circularity in the window dependency graph\.$/', 'ER_WINDOW_CIRCULARITY_IN_DEPENDENCY_GRAPH: window references are resolved during analysis.'],
        [3581, '/^A window which depends on another cannot define partitioning\.$/', 'ER_WINDOW_NO_CHILD_PARTITIONING: window references are resolved during analysis.'],
        [3587, '/^Window \'.*\' with RANGE N PRECEDING\/FOLLOWING frame requires exactly one ORDER BY expression, of numeric or temporal type$/', 'ER_WINDOW_RANGE_FRAME_ORDER_TYPE: window frames are checked during analysis.'],
        [3591, '/^Window \'.*\' is defined twice\.$/', 'ER_WINDOW_DUPLICATE_NAME: generated window lists may repeat a name.'],
        [3998, '/^Cannot cast value to TIMESTAMP WITH TIME ZONE\.$/', 'ER_INVALID_CAST_TO_TIMESTAMP: cast targets are checked during analysis.'],
    ];

    /**
     * Client errors that mean the server went away rather than that the statement was rejected.
     *
     * @var list<int>
     */
    public const CONNECTION_ERRORS = [1040, 2002, 2006, 2013];

    /**
     * @param PDO $pdo Connection to the MySQL instance under test
     * @param string $grammarVersion Grammar version that produced the statement, e.g. "mysql-8.4.7"
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
     * @param string $input Fuzzer input that produced the statement, so a finding can be replayed
     *
     * @throws Error When MySQL rejects the statement for a reason the grammar should not produce
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\nInput (hex): " . bin2hex($input));
        }
        try {
            $quoted = $this->pdo->quote($sql);
            if ($quoted === false) {
                fwrite(STDERR, "Cannot quote generated SQL for server PREPARE.\n");
                exit(2);
            }
            $this->pdo->exec('SET @sql_faker_input = ' . $quoted);
            $this->pdo->exec('PREPARE sql_faker_check FROM @sql_faker_input');
            $this->pdo->exec('DEALLOCATE PREPARE sql_faker_check');
        } catch (PDOException $rejection) {
            $info = $rejection->errorInfo ?? [];
            $code = $info[1] ?? 0;
            $code = is_int($code) ? $code : 0;
            $detail = $info[2] ?? '';
            $detail = is_string($detail) ? $detail : '';
            if (in_array($code, self::CONNECTION_ERRORS, true)) {
                fwrite(STDERR, "MySQL connection failed: {$rejection->getMessage()}\n");
                exit(2);
            }
            if (isset(self::IGNORED_CODES[$code])) {
                return;
            }
            foreach (self::IGNORED_MESSAGES as [$ignoredCode, $pattern, $reason]) {
                if ($code === $ignoredCode && preg_match($pattern, $detail) === 1) {
                    return;
                }
            }
            throw new Error(
                "Unexpected error in generated SQL\n" .
                "Grammar: {$this->grammarVersion}\n" .
                'Input (hex): ' . bin2hex($input) . "\n" .
                "SQL: $sql\n" .
                'SQLSTATE: ' . (is_scalar($info[0] ?? null) ? (string) $info[0] : 'unknown') . "\n" .
                "Error Code: $code\n" .
                "Error: {$rejection->getMessage()}",
                0,
                $rejection
            );
        }
    }
}
