<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use PgSql\Connection;

/**
 * Sends only Parse and Sync to PostgreSQL; generated SQL is never executed.
 *
 * The unnamed prepared statement is replaced for each input. There is no
 * transaction, savepoint or SQL session state carried between cases.
 */
final class PgSyntaxCheck
{
    /**
     * Binds one fixed disposable PostgreSQL instance for the entire run.
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Verifies original SQL using the server's extended query protocol.
     *
     * @throws InfrastructureFailure When the connection cannot complete Parse
     * @throws SyntaxFailure When syntax or an unclassified server rejection is observed
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new SyntaxFailure('Statement generation returned an empty string.');
        }
        if (pg_connection_status($this->connection) !== PGSQL_CONNECTION_OK
            || pg_send_prepare($this->connection, '', $sql) === false) {
            throw new InfrastructureFailure('PostgreSQL Parse connection is unavailable.');
        }
        $result = pg_get_result($this->connection);
        if ($result === false) {
            throw new InfrastructureFailure('PostgreSQL Parse returned no result.');
        }
        $state = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
        $error = pg_result_error($result);
        $message = is_string($error) ? $error : 'No server error text.';
        $status = pg_result_status($result);
        pg_free_result($result);
        while (($extra = pg_get_result($this->connection)) !== false) {
            pg_free_result($extra);
        }
        if ($status === PGSQL_COMMAND_OK) {
            return;
        }
        if (!is_string($state) || str_starts_with($state, '08') || in_array($state, ['57P01', '57P02', '57P03'], true)) {
            throw new InfrastructureFailure('PostgreSQL Parse failed: ' . $message);
        }
        self::rejection($state, $message, $sql, $input);
    }

    /**
     * Classifies known semantic rejections separately from unexpected syntax errors.
     *
     * The grammar admits DEFAULT as an expression; parse analysis checks its context
     * and reports 42601 too. parse_target.c, define.c and parse_merge.c likewise
     * reject missing relations, option values and an unsupported MERGE feature.
     * parse_coerce.c reports 42804 for incompatible expression types after parsing.
     * parse_expr.c reports 42P18 when an empty array has no inferable element type.
     * parse_merge.c checks unreachable WHEN clauses; parse_clause.c rejects duplicate window names.
     * Datetime input conversion and overloaded function resolution report 22007 and 42725 after parsing.
     * parse_expr.c checks scalar subquery width after target-list analysis.
     * parse_type.c validates resolved type modifiers; parse_relation.c checks a function's result type.
     * parse_expr.c validates MERGE_ACTION against the current analyzed expression context.
     * parse_clause.c resolves non-integer sort/group positions; parse_cte.c checks whether SEARCH/CYCLE queries are recursive.
     * Only those specific diagnostics are inconclusive; grammar-action errors remain findings.
     *
     * @throws SyntaxFailure When syntax or an unclassified rejection is observed
     */
    public static function rejection(string $state, string $message, string $sql, string $input): void
    {
        if (in_array($state, ['42704', '42P01', '42703', '3F000', '42809', '22023', '26000',
            '2BP01', '42602', '42883', '42939', '42P07', '42P10', '3D000', '42P03', '22P02',
            '42712', '42P19', '42P11', '42804', '42P18', '22007', '42725'], true)) {
            return;
        }
        if ($state === '0A000') {
            return;
        }
        if ($state === '42601' && (str_starts_with(trim($message), 'ERROR:  DEFAULT is not allowed in this context')
            || preg_match('/\AERROR:  (?:non-integer constant in (?:GROUP BY|ORDER BY|DISTINCT ON)|WITH query is not recursive)(?:\r?\n|\z)/D', trim($message)) === 1
            || preg_match('/\AERROR:  subquery must return only one column(?:\r?\n|\z)/D', trim($message)) === 1
            || preg_match('/\AERROR:  (?:type modifiers must be simple constants or identifiers|a column definition list is only allowed for functions returning "record"|MERGE_ACTION\(\) can only be used in the RETURNING list of a MERGE command)(?:\r?\n|\z)/D', trim($message)) === 1
            || str_starts_with(trim($message), 'ERROR:  format requires a parameter')
            || str_starts_with(trim($message), 'ERROR:  SELECT * with no tables specified is not valid')
            || str_starts_with(trim($message), 'ERROR:  unreachable WHEN clause specified after unconditional WHEN clause')
            || str_starts_with(trim($message), 'ERROR:  WITH RECURSIVE is not supported for MERGE statement'))) {
            return;
        }
        if ($state === '42P20' && preg_match('/\AERROR:  window "[^\r\n]*" is already defined(?:\r?\n|\z)/D', trim($message)) === 1) {
            return;
        }
        throw new SyntaxFailure("PostgreSQL syntax verification failed\nInput (hex): $input\nSQL: $sql\nSQLSTATE: $state\n$message");
    }
}
