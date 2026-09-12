<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PgSql\Connection;

/**
 * Parses generated SQL on the PostgreSQL server under test and reports unexpected rejections.
 *
 * The statement is sent as an extended-protocol Parse message and never executed. A fuzz
 * run has no schema, so the SQLSTATEs the grammar cannot avoid are listed below with the
 * reason each one is tolerated. Every other rejection is a finding: it surfaces as an Error
 * for PHP-Fuzzer to record together with the input.
 */
final class PgSyntaxCheck
{
    /**
     * Server errors tolerated for any statement, keyed by SQLSTATE.
     *
     * All of them are raised after the grammar accepted the statement, while the server
     * resolves names or checks semantics; 0A000 comes from grammar actions and analysis
     * that reject a feature the grammar still spells out.
     *
     * @var array<int|string, string> PHP stores all-digit SQLSTATEs such as 22023 as integer keys
     */
    public const IGNORED_STATES = [
        '0A000' => 'feature_not_supported: the statement parsed and an action or the analyzer refused the feature.',
        '22007' => 'invalid_datetime_format: date and time literals are generated strings.',
        '22023' => 'invalid_parameter_value: option values are generated literals.',
        '22P02' => 'invalid_text_representation: typed literals are generated strings.',
        '26000' => 'invalid_sql_statement_name: prepared statement names are generated identifiers.',
        '2BP01' => 'dependent_objects_still_exist: object names are generated, the catalog is empty.',
        '3D000' => 'invalid_catalog_name: database names are generated, none exists.',
        '3F000' => 'invalid_schema_name: schema names are generated, none exists.',
        '42602' => 'invalid_name: generated names may violate naming restrictions checked after parsing.',
        '42703' => 'undefined_column: column names are generated, none exists.',
        '42704' => 'undefined_object: object names are generated, none exists.',
        '42712' => 'duplicate_alias: generated table references may repeat an alias.',
        '42725' => 'ambiguous_function: function names are resolved during analysis.',
        '42803' => 'grouping_error: aggregate placement is checked during analysis.',
        '42804' => 'datatype_mismatch: types are checked during analysis.',
        '42809' => 'wrong_object_type: object kinds are checked during analysis.',
        '42846' => 'cannot_coerce: casts are checked during analysis.',
        '42883' => 'undefined_function: function names are generated, none exists.',
        '42939' => 'reserved_name: generated names may hit reserved schema names.',
        '42P01' => 'undefined_table: table names are generated, none exists.',
        '42P03' => 'duplicate_cursor: cursor names are generated identifiers.',
        '42P07' => 'duplicate_table: generated names may collide within one statement.',
        '42P10' => 'invalid_column_reference: column references are checked during analysis.',
        '42P11' => 'invalid_cursor_definition: cursor options are checked after parsing.',
        '42P18' => 'indeterminate_datatype: parameter types are inferred during analysis.',
        '42P19' => 'invalid_recursion: recursive CTE shape is checked after parsing.',
    ];

    /**
     * Server errors tolerated only with a specific message, as [SQLSTATE, message pattern, reason].
     *
     * The analyzer reports these with the generic syntax_error and windowing_error states,
     * so the message decides.
     *
     * @var list<array{string, string, string}>
     */
    public const IGNORED_MESSAGES = [
        ['42601', '/^ERROR:  DEFAULT is not allowed in this context/', 'DEFAULT placement is checked by the analyzer.'],
        ['42601', '/^ERROR:  (?:non-integer constant in (?:GROUP BY|ORDER BY|DISTINCT ON)|WITH query is not recursive)(?:\r?\n|$)/', 'ordinal and CTE references are checked by the analyzer.'],
        ['42601', '/^ERROR:  subquery must return only one column(?:\r?\n|$)/', 'subquery width is checked by the analyzer.'],
        ['42601', '/^ERROR:  (?:type modifiers must be simple constants or identifiers|a column definition list is only allowed for functions returning "record"|MERGE_ACTION\(\) can only be used in the RETURNING list of a MERGE command)(?:\r?\n|$)/', 'type modifiers and function contexts are checked by the analyzer.'],
        ['42601', '/^ERROR:  syntax error (?:at end of jsonpath input|at or near "[^\r\n]*" of jsonpath input)(?:\r?\n|$)/', 'jsonpath text inside a string literal is parsed by the jsonpath scanner, not the SQL grammar.'],
        ['42601', '/^ERROR:  format requires a parameter/', 'COPY and EXPLAIN options are checked by the analyzer.'],
        ['42601', '/^ERROR:  SELECT \* with no tables specified is not valid/', 'the analyzer needs a table where the grammar allows none.'],
        ['42601', '/^ERROR:  unreachable WHEN clause specified after unconditional WHEN clause/', 'MERGE clause order is checked by the analyzer.'],
        ['42601', '/^ERROR:  WITH RECURSIVE is not supported for MERGE statement/', 'MERGE restrictions are checked by the analyzer.'],
        ['42P20', '/^ERROR:  (?:window "[^\r\n]*" is already defined|GROUPS mode requires an ORDER BY clause)(?:\r?\n|$)/', 'window definitions are checked by the analyzer.'],
    ];

    /**
     * @param Connection $connection Connection to the PostgreSQL instance under test
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Verifies that PostgreSQL parses the generated statement.
     *
     * @param string $sql Statement produced by the grammar
     * @param string $input Fuzzer input that produced the statement, so a finding can be replayed
     *
     * @throws Error When PostgreSQL rejects the statement for a reason the grammar should not produce
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\nInput (hex): " . bin2hex($input));
        }
        if (pg_connection_status($this->connection) !== PGSQL_CONNECTION_OK || pg_send_prepare($this->connection, '', $sql) === false) {
            fwrite(STDERR, 'PostgreSQL connection failed: ' . pg_last_error($this->connection) . "\n");
            exit(2);
        }
        $result = pg_get_result($this->connection);
        if ($result === false) {
            fwrite(STDERR, "PostgreSQL Parse returned no result.\n");
            exit(2);
        }
        $status = pg_result_status($result);
        $state = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
        $error = pg_result_error($result);
        $message = is_string($error) ? trim($error) : 'No server error text.';
        pg_free_result($result);
        while (($extra = pg_get_result($this->connection)) !== false) {
            pg_free_result($extra);
        }
        if ($status === PGSQL_COMMAND_OK) {
            return;
        }
        $state = is_string($state) ? $state : '';
        if (str_starts_with($state, '08') || in_array($state, ['57P01', '57P02', '57P03'], true)) {
            fwrite(STDERR, "PostgreSQL connection failed: $message\n");
            exit(2);
        }
        if (isset(self::IGNORED_STATES[$state])) {
            return;
        }
        foreach (self::IGNORED_MESSAGES as [$ignoredState, $pattern, $reason]) {
            if ($state === $ignoredState && preg_match($pattern, $message) === 1) {
                return;
            }
        }
        throw new Error(
            "Unexpected error in generated SQL\n" .
            'Input (hex): ' . bin2hex($input) . "\n" .
            "SQL: $sql\n" .
            "SQLSTATE: $state\n" .
            "Error: $message"
        );
    }
}
