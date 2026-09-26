<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PgSql\Connection;
use SqlFormatter\Core\FormattingException;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\Lexer\SourceException;

/**
 * Runs a generated statement and its formatted text on PostgreSQL and reports a different answer.
 *
 * Every statement runs inside a transaction that is rolled back, so DDL and DML leave nothing
 * behind; a statement PostgreSQL refuses inside a transaction block fails the same way both
 * times. Errors are compared by SQLSTATE and primary message, which carry no position. A
 * mismatch is confirmed by running the original once more, so a statement that reads the
 * clock is not reported.
 */
final class PgEquivalence
{
    /**
     * Session settings applied to every connection so that a runaway statement ends in time.
     *
     * @var list<string>
     */
    public const SETTINGS = ['SET statement_timeout = 2000', 'SET lock_timeout = 2000', "SET client_min_messages = 'error'"];

    private Connection $connection;

    /**
     * @param string $dsn Connection string for pg_connect
     * @param Formatter $formatter The formatter under test
     * @param Style $style The layout preset in use, for findings
     */
    public function __construct(
        private readonly string $dsn,
        private readonly Formatter $formatter,
        private readonly Style $style,
    ) {
        $this->connection = $this->connect();
    }

    /**
     * Verifies that PostgreSQL answers the formatted statement as it answers the original.
     *
     * @param string $sql Statement produced by the grammar
     * @param string $input Fuzzer input that produced the statement, so a finding can be replayed
     *
     * @throws Error When the statement is empty, formatting fails verification, or the answers differ
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\nInput (hex): " . bin2hex($input));
        }
        $context = "Grammar: pg-17.2\nStyle: {$this->style->value}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        try {
            $formatted = $this->formatter->format($sql);
        } catch (SourceException) {
            return;
        } catch (FormattingException $failure) {
            throw new Error("Formatting failed verification\n{$context}\nError: {$failure->getMessage()}", 0, $failure);
        }
        $before = $this->run($sql);
        $after = $this->run($formatted);
        if ($before === $after || $this->run($sql) !== $before) {
            return;
        }
        throw new Error(
            "Formatted SQL behaves differently on PostgreSQL\n{$context}\nFormatted: {$formatted}\n" .
            'Before: ' . json_encode($before, JSON_INVALID_UTF8_SUBSTITUTE) . "\n" .
            'After: ' . json_encode($after, JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }

    /**
     * Runs one statement inside a transaction that is rolled back afterwards.
     *
     * A statement that leaves the session busy, such as a COPY, or outside an idle
     * transaction state is followed by a fresh connection.
     *
     * @return array{string, mixed}|array{string, string, string} ["ok", rows or command tag] or ["error", SQLSTATE, message]
     */
    public function run(string $sql): array
    {
        $this->execute('BEGIN');
        $answer = $this->execute($sql);
        if (pg_transaction_status($this->connection) === PGSQL_TRANSACTION_ACTIVE) {
            $this->connection = $this->connect();
            return $answer;
        }
        $this->execute('ROLLBACK');
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            $this->connection = $this->connect();
        }
        return $answer;
    }

    /**
     * Sends one statement and reads every result it produced, keeping the last.
     *
     * The statement is sent asynchronously so that a server error never becomes a PHP warning.
     *
     * @return array{string, mixed}|array{string, string, string} ["ok", rows or command tag] or ["error", SQLSTATE, message]
     */
    public function execute(string $sql): array
    {
        if (pg_connection_status($this->connection) !== PGSQL_CONNECTION_OK || pg_send_query($this->connection, $sql) === false) {
            fwrite(STDERR, 'PostgreSQL connection failed: ' . pg_last_error($this->connection) . "\n");
            exit(2);
        }
        $answer = ['ok', 'EMPTY'];
        while (($result = pg_get_result($this->connection)) !== false) {
            $status = pg_result_status($result);
            if ($status === PGSQL_TUPLES_OK) {
                $answer = ['ok', pg_fetch_all($result, PGSQL_NUM)];
            } elseif ($status === PGSQL_COMMAND_OK) {
                $answer = ['ok', pg_result_status($result, PGSQL_STATUS_STRING)];
            } elseif ($status === PGSQL_COPY_IN || $status === PGSQL_COPY_OUT) {
                pg_free_result($result);
                return ['ok', 'COPY'];
            } elseif ($status !== PGSQL_EMPTY_QUERY) {
                $state = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
                $state = is_string($state) ? $state : '';
                $message = pg_result_error_field($result, PGSQL_DIAG_MESSAGE_PRIMARY);
                $message = is_string($message) ? $message : 'No server error text.';
                if (str_starts_with($state, '08') || str_starts_with($state, '57P0')) {
                    fwrite(STDERR, "PostgreSQL connection failed: {$message}\n");
                    exit(2);
                }
                $answer = ['error', $state, $message];
            }
            pg_free_result($result);
        }
        return $answer;
    }

    /**
     * Opens a connection with the session settings, ending the run when the server is unreachable.
     */
    public function connect(): Connection
    {
        $connection = @pg_connect($this->dsn, PGSQL_CONNECT_FORCE_NEW);
        if ($connection === false) {
            fwrite(STDERR, "Cannot connect to PostgreSQL with \"{$this->dsn}\".\n");
            exit(2);
        }
        $this->connection = $connection;
        foreach (self::SETTINGS as $setting) {
            $this->execute($setting);
        }
        return $connection;
    }
}
