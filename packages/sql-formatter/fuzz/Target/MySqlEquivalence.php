<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use PDOException;
use SqlFormatter\Formatter;
use SqlFormatter\FormattingException;
use SqlFormatter\Style;
use SqlParser\Lexer\SourceException;

/**
 * Runs a generated statement and its formatted text on MySQL and reports a different answer.
 *
 * MySQL cannot undo most statements, and the server may be shared with other test runs,
 * so statements run as a user of their own with rights on a database of their own only:
 * the server denies everything that would reach beyond it, such as SHUTDOWN, KILL of other
 * sessions or SET GLOBAL, and the database is dropped and created again before every
 * statement, so both texts meet the same empty schema. A statement that ends the
 * connection, or changes the fuzz user's own password, is followed by a fresh user and
 * connection. A mismatch is confirmed by running the original once more, so a statement
 * that reads the clock is not reported.
 */
final class MySqlEquivalence
{
    /**
     * The user and database the statements run in, recreated whenever the connection is replaced.
     */
    public const USER = 'sql_formatter_fuzz';

    /**
     * Client and server errors after which the connection is replaced rather than compared:
     * the server went away, the connection was killed, or the fuzz user cannot log in any more.
     *
     * @var list<int>
     */
    public const CONNECTION_ERRORS = [1040, 1045, 1820, 2002, 2006, 2013, 3118];

    private PDO $pdo;

    /**
     * @param string $dsn PDO data source name of the MySQL server under test, without a database
     * @param string $rootPassword Password of the root account that creates the fuzz user
     * @param Formatter $formatter The formatter under test
     * @param Style $style The layout preset in use, for findings
     * @param string $grammarVersion Grammar version that produced the statement, e.g. "mysql-8.4.7"
     */
    public function __construct(
        private readonly string $dsn,
        private readonly string $rootPassword,
        private readonly Formatter $formatter,
        private readonly Style $style,
        private readonly string $grammarVersion,
    ) {
        $this->pdo = $this->connect();
    }

    /**
     * Verifies that MySQL answers the formatted statement as it answers the original.
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
        $context = "Grammar: {$this->grammarVersion}\nStyle: {$this->style->value}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        try {
            $formatted = $this->formatter->format($sql);
        } catch (SourceException) {
            return;
        } catch (FormattingException $failure) {
            throw new Error("Formatting failed verification\n{$context}\nError: {$failure->getMessage()}", 0, $failure);
        }
        $before = $this->execute($sql);
        $after = $this->execute($formatted);
        if ($before === $after || $this->execute($sql) !== $before) {
            return;
        }
        throw new Error(
            "Formatted SQL behaves differently on MySQL\n{$context}\nFormatted: {$formatted}\n" .
            'Before: ' . json_encode($before, JSON_INVALID_UTF8_SUBSTITUTE) . "\n" .
            'After: ' . json_encode($after, JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }

    /**
     * Executes one statement on a recreated database and answers its rows, its affected row count, or its error.
     *
     * A parse error quotes the remaining text and names its line; both are the statement's
     * own layout, so they are dropped before the message is compared.
     *
     * @return array{string, mixed}|array{string, int, string} ["ok", rows or count], ["error", code, message] or ["lost", code]
     */
    public function execute(string $sql): array
    {
        try {
            $this->pdo->exec('DROP DATABASE IF EXISTS ' . self::USER);
            $this->pdo->exec('CREATE DATABASE ' . self::USER);
            $this->pdo->exec('USE ' . self::USER);
            $statement = $this->pdo->query($sql);
            if ($statement === false) {
                fwrite(STDERR, "PDO::query returned false without throwing.\n");
                exit(2);
            }
            $answer = $statement->columnCount() > 0 ? $statement->fetchAll(PDO::FETCH_NUM) : $statement->rowCount();
            $statement->closeCursor();
            return ['ok', $answer];
        } catch (PDOException $rejection) {
            $code = $rejection->errorInfo[1] ?? 0;
            $code = is_int($code) ? $code : 0;
            if (in_array($code, self::CONNECTION_ERRORS, true)) {
                $this->pdo = $this->connect();
                return ['lost', $code];
            }
            $message = $rejection->errorInfo[2] ?? null;
            $message = is_string($message) ? $message : $rejection->getMessage();
            $message = preg_replace("/ near '.*' at line \\d+$/s", '', $message) ?? $message;
            return ['error', $code, $message];
        }
    }

    /**
     * Recreates the fuzz user with rights on its own database and opens a connection as that user.
     *
     * The run ends when the root account cannot do so, because the server is then gone.
     */
    public function connect(): PDO
    {
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
        $user = self::USER;
        try {
            $root = new PDO($this->dsn, 'root', $this->rootPassword, $options);
            $root->exec("DROP USER IF EXISTS '{$user}'@'%'");
            $root->exec("CREATE USER '{$user}'@'%' IDENTIFIED BY '{$user}'");
            $root->exec("GRANT ALL ON `{$user}`.* TO '{$user}'@'%'");
            $pdo = new PDO($this->dsn, $user, $user, $options);
            $pdo->exec('SET SESSION max_execution_time = 2000');
            return $pdo;
        } catch (PDOException $failure) {
            fwrite(STDERR, "MySQL connection failed: {$failure->getMessage()}\n");
            exit(2);
        }
    }
}
