<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use Override;
use PgSql\Connection;
use SqlFaker\Coverage\Verification\VerificationResult;

/**
 * Calls PostgreSQL 17.2 raw_parser in a declared parser mode, without parse analysis or SQL execution.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/include/parser/parser.h
 */
final class PgRawCheck implements SyntaxCheck
{
    /**
     * Installs only a session-local entry point to the prebuilt, version-checked helper module.
     * @throws InfrastructureFailure When the pinned helper is unavailable
     */
    public function __construct(private readonly Connection $connection, private readonly int $mode, string $module = '/tmp/sqlfaker_raw_parse.so')
    {
        OracleEnvironment::pg($connection);
        if ($mode < 0 || $mode > 5) {
            throw new InfrastructureFailure('Unknown PostgreSQL raw parser mode.');
        }
        $path = pg_escape_literal($connection, $module);
        $sql = "CREATE OR REPLACE FUNCTION pg_temp.sqlfaker_raw_parse(text, integer) RETURNS integer AS $path, 'sqlfaker_raw_parse' LANGUAGE C STRICT";
        if (pg_send_query($connection, $sql) === false) {
            throw new InfrastructureFailure('Cannot initialize PostgreSQL raw parser.');
        }
        $result = pg_get_result($connection);
        $success = $result !== false && pg_result_status($result) === PGSQL_COMMAND_OK;
        $message = $result === false ? 'no result' : pg_result_error($result);
        if ($result !== false) {
            pg_free_result($result);
        }
        while (($extra = pg_get_result($connection)) !== false) {
            pg_free_result($extra);
        }
        if (!$success) {
            throw new InfrastructureFailure('Cannot initialize PostgreSQL raw parser: ' . $message);
        }
    }

    /**
     * @throws InfrastructureFailure When the parser transport is unavailable
     * @throws SyntaxFailure When the raw parser rejects syntax or an unclassified grammar action
     */
    #[Override]
    public function verify(string $sql, string $input): VerificationResult
    {
        if (pg_send_query_params($this->connection, 'SELECT pg_temp.sqlfaker_raw_parse($1, $2)', [$sql, $this->mode]) === false) {
            throw new InfrastructureFailure('Cannot send PostgreSQL raw parse request.');
        }
        $result = pg_get_result($this->connection);
        if ($result === false) {
            throw new InfrastructureFailure('PostgreSQL raw parser returned no result.');
        }
        $status = pg_result_status($result);
        $diagnostic = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
        $state = is_string($diagnostic) ? $diagnostic : 'XX000';
        $file = pg_result_error_field($result, PGSQL_DIAG_SOURCE_FILE);
        $source = is_string($file) ? $file : '';
        $error = pg_result_error($result);
        $message = is_string($error) ? $error : 'No server diagnostic.';
        pg_free_result($result);
        while (($extra = pg_get_result($this->connection)) !== false) {
            pg_free_result($extra);
        }
        if (str_starts_with($state, '08') || in_array($state, ['57P01', '57P02', '57P03'], true)) {
            throw new InfrastructureFailure('PostgreSQL raw parser connection failed: ' . $message);
        }
        return $status === PGSQL_TUPLES_OK ? new VerificationResult('accepted') : PgSyntaxCheck::rejection($state, $message, $sql, $input, $source);
    }
}
