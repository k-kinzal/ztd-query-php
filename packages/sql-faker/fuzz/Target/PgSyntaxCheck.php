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
        pg_free_result($result);
        while (($extra = pg_get_result($this->connection)) !== false) {
            pg_free_result($extra);
        }
        if ($status === PGSQL_COMMAND_OK) {
            return VerificationResult::Accepted;
        }
        if (!is_string($state) || str_starts_with($state, '08') || in_array($state, ['57P01', '57P02', '57P03'], true)) {
            throw new InfrastructureFailure('PostgreSQL Parse failed: ' . $message);
        }
        return self::rejection($state, $message, $sql, $input);
    }

    /**
     * Classifies server rejections without treating syntax errors as expected state.
     *
     * @throws SyntaxFailure When syntax or an unclassified rejection is observed
     */
    public static function rejection(string $state, string $message, string $sql, string $input): VerificationResult
    {
        if (in_array($state, ['42704', '42P01', '42703', '3F000', '42809', '22023', '26000',
            '2BP01', '42602', '42883', '42939', '42P07', '42P10', '3D000', '42P03', '22P02'], true)) {
            return VerificationResult::Rejected;
        }
        if ($state === '0A000') {
            return VerificationResult::Incomplete;
        }
        throw new SyntaxFailure("PostgreSQL syntax verification failed\nInput (hex): $input\nSQL: $sql\nSQLSTATE: $state\n$message");
    }
}
