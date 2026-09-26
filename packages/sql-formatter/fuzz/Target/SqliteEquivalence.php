<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use PDOException;
use SqlFormatter\Core\FormattingException;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\Lexer\SourceException;

/**
 * Runs a generated statement and its formatted text on SQLite and reports a different answer.
 *
 * Every statement runs on its own empty in-memory database, and file names it may carry,
 * such as the target of `ATTACH` or `VACUUM INTO`, resolve inside a scratch directory. A
 * mismatch is confirmed by running the original once more, so a statement that reads the
 * clock is not reported.
 */
final class SqliteEquivalence
{
    /**
     * @param string $scratch Directory the process changes into while a statement runs
     * @param Formatter $formatter The formatter under test
     * @param Style $style The layout preset in use, for findings
     */
    public function __construct(
        private readonly string $scratch,
        private readonly Formatter $formatter,
        private readonly Style $style,
    ) {
    }

    /**
     * Verifies that SQLite answers the formatted statement as it answers the original.
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
        $context = "Grammar: sqlite-3.47.2\nStyle: {$this->style->value}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
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
            "Formatted SQL behaves differently on SQLite\n{$context}\nFormatted: {$formatted}\n" .
            'Before: ' . json_encode($before, JSON_INVALID_UTF8_SUBSTITUTE) . "\n" .
            'After: ' . json_encode($after, JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }

    /**
     * Executes one statement on a new in-memory database and answers its rows, its affected row count, or its error.
     *
     * @return array{string, mixed}|array{string, int, string} ["ok", rows or count] or ["error", code, message]
     */
    public function execute(string $sql): array
    {
        $directory = getcwd();
        if ($directory === false || !chdir($this->scratch)) {
            fwrite(STDERR, "Cannot enter the scratch directory {$this->scratch}.\n");
            exit(2);
        }
        try {
            $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $statement = $pdo->query($sql);
            if ($statement === false) {
                fwrite(STDERR, "PDO::query returned false without throwing.\n");
                exit(2);
            }
            return ['ok', $statement->columnCount() > 0 ? $statement->fetchAll(PDO::FETCH_NUM) : $statement->rowCount()];
        } catch (PDOException $rejection) {
            $code = $rejection->errorInfo[1] ?? 0;
            $message = $rejection->errorInfo[2] ?? null;
            return ['error', is_int($code) ? $code : 0, trim(is_string($message) ? $message : $rejection->getMessage())];
        } finally {
            chdir($directory);
        }
    }
}
