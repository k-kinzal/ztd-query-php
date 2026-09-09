<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use Override;
use PgSql\Connection;
use SqlFaker\Coverage\Verification\VerificationResult;

/**
 * Sends only Parse and Sync to PostgreSQL; generated SQL is never executed.
 *
 * The unnamed prepared statement is replaced for each input. There is no
 * transaction, savepoint or SQL session state carried between cases.
 */
final class PgSyntaxCheck implements SyntaxCheck
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
    #[Override]
    public function verify(string $sql, string $input): VerificationResult
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
        $source = pg_result_error_field($result, PGSQL_DIAG_SOURCE_FILE);
        pg_free_result($result);
        while (($extra = pg_get_result($this->connection)) !== false) {
            pg_free_result($extra);
        }
        if ($status === PGSQL_COMMAND_OK) {
            return new VerificationResult('accepted');
        }
        if (!is_string($state) || str_starts_with($state, '08') || in_array($state, ['57P01', '57P02', '57P03'], true)) {
            throw new InfrastructureFailure('PostgreSQL Parse failed: ' . $message);
        }
        return self::rejection($state, $message, $sql, $input, is_string($source) ? $source : '');
    }

    /**
     * Classifies known semantic rejections separately from unexpected syntax errors.
     * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
     * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/analyze.c
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
     * parse_expr.c rejects unresolved explicit casts with 42846 after resolving both types.
     * parse_agg.c checks aggregate/grouping expression contexts after function analysis.
     * parse_clause.c checks GROUPS against the resolved window ordering, including inherited clauses.
     * jsonpath_scan.l reports 42601 for invalid JSONPath string values after SQL parsing.
     * Only those specific diagnostics are inconclusive; grammar-action errors remain findings.
     *
     * @throws SyntaxFailure When syntax or an unclassified rejection is observed
     */
    public static function rejection(string $state, string $message, string $sql, string $input, string $source = ''): VerificationResult
    {
        $primary = preg_replace('/\AERROR:\s*/', '', explode("\n", trim($message), 2)[0]);
        if ($state === '0A000' && in_array($primary, [
            'UNENCRYPTED PASSWORD is no longer supported', 'current database cannot be changed',
            'MATCH PARTIAL not yet implemented', 'CREATE EXTENSION ... FROM is no longer supported',
            'CREATE ASSERTION is not yet implemented', 'dropping an enum value is not implemented',
            'UNIQUE predicate is not yet implemented',
        ], true)) {
            return new VerificationResult('unsupported', $state, $message, $source);
        }
        if (in_array(basename($source), ['gram.y', 'gram.c', 'scan.l', 'scan.c', 'parser.c'], true)) {
            throw new SyntaxFailure("PostgreSQL grammar or scanner rejected generated SQL\nInput (hex): $input\nSQL: $sql\nSQLSTATE: $state\nSource: $source\n$message");
        }
        if (in_array($state, ['42704', '42P01', '42703', '3F000', '42809', '22023', '26000',
            '2BP01', '42602', '42883', '42939', '42P07', '42P10', '3D000', '42P03', '22P02',
            '42712', '42P19', '42P11', '42804', '42P18', '22007', '42725', '42803', '42846'], true)) {
            return new VerificationResult('semantic-inconclusive', $state, $message, $source);
        }
        if ($state === '0A000' && in_array(basename($source), [
            'parse_expr.c', 'parse_agg.c', 'parse_clause.c', 'parse_func.c', 'parse_coerce.c', 'parse_cte.c',
            'parse_target.c', 'parse_relation.c', 'parse_utilcmd.c', 'analyze.c',
        ], true)) {
            return new VerificationResult('semantic-inconclusive', $state, $message, $source);
        }
        if ($state === '42601' && (str_starts_with(trim($message), 'ERROR:  DEFAULT is not allowed in this context')
            || preg_match('/\AERROR:  (?:non-integer constant in (?:GROUP BY|ORDER BY|DISTINCT ON)|WITH query is not recursive)(?:\r?\n|\z)/D', trim($message)) === 1
            || preg_match('/\AERROR:  subquery must return only one column(?:\r?\n|\z)/D', trim($message)) === 1
            || preg_match('/\AERROR:  (?:type modifiers must be simple constants or identifiers|a column definition list is only allowed for functions returning "record"|MERGE_ACTION\(\) can only be used in the RETURNING list of a MERGE command)(?:\r?\n|\z)/D', trim($message)) === 1
            || preg_match('/\AERROR:  syntax error (?:at end of jsonpath input|at or near "[^\r\n]*" of jsonpath input)(?:\r?\n|\z)/D', trim($message)) === 1
            || str_starts_with(trim($message), 'ERROR:  format requires a parameter')
            || str_starts_with(trim($message), 'ERROR:  SELECT * with no tables specified is not valid')
            || str_starts_with(trim($message), 'ERROR:  unreachable WHEN clause specified after unconditional WHEN clause')
            || str_starts_with(trim($message), 'ERROR:  WITH RECURSIVE is not supported for MERGE statement'))) {
            return new VerificationResult('semantic-inconclusive', $state, $message, $source);
        }
        if ($state === '42P20' && preg_match('/\AERROR:  (?:window "[^\r\n]*" is already defined|GROUPS mode requires an ORDER BY clause)(?:\r?\n|\z)/D', trim($message)) === 1) {
            return new VerificationResult('semantic-inconclusive', $state, $message, $source);
        }
        throw new SyntaxFailure("PostgreSQL syntax verification failed\nInput (hex): $input\nSQL: $sql\nSQLSTATE: $state\n$message");
    }
}
