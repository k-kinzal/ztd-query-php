<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use PDOException;

/**
 * Runs one statement on a MySQL server and on mysql-memory, from the same fixture, and requires every observation to be equal.
 *
 * The statement runs twice on the MySQL server, each time on a fresh database; when the two
 * observations differ the statement is volatile (it reads the clock, a random number, or a
 * server identity) and is not compared. Otherwise the observation of mysql-memory must equal it:
 * the result columns and rows, or the error, the warnings, and the rows of every table after.
 */
final class Differential
{
    /**
     * The database each input runs in.
     */
    public const DATABASE = 'fz';

    /**
     * @param string $native The PDO DSN of the MySQL server, without a database
     * @param string $nativeUser The user of the MySQL server
     * @param string $nativePassword The password of the MySQL server
     * @param string $memory The PDO DSN of mysql-memory, without a database
     * @param bool $emulate Whether PDO emulates prepared statements
     */
    public function __construct(
        public readonly string $native,
        public readonly string $nativeUser,
        public readonly string $nativePassword,
        public readonly string $memory,
        public readonly bool $emulate = true,
    ) {
    }

    /**
     * Compares one statement, throwing when the observations differ.
     *
     * @throws Error When mysql-memory observes something else than the MySQL server
     */
    public function verify(string $sql, string $input = ''): void
    {
        $difference = $this->difference($sql);
        if ($difference !== null) {
            throw new Error("mysql-memory differs from MySQL.\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}\n" . $difference);
        }
    }

    /**
     * Answers how the observations differ, or null when they are equal or the statement is volatile.
     */
    public function difference(string $sql): ?string
    {
        $expected = $this->run($this->native, $this->nativeUser, $this->nativePassword, $sql);
        $again = $this->run($this->native, $this->nativeUser, $this->nativePassword, $sql);
        if ($expected !== $again) {
            return null;
        }
        $actual = $this->run($this->memory, 'root', '', $sql);
        if ($expected === $actual) {
            return null;
        }
        $lines = [];
        foreach (array_unique([...array_keys($expected), ...array_keys($actual)]) as $key) {
            if (($expected[$key] ?? null) !== ($actual[$key] ?? null)) {
                $lines[] = $key . "\n  expected: " . $this->json($expected[$key] ?? null) . "\n  actual:   " . $this->json($actual[$key] ?? null);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Writes a value as compact JSON for a report.
     */
    public function json(mixed $value): string
    {
        $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return strlen((string) $text) > 600 ? substr((string) $text, 0, 600) . '...' : (string) $text;
    }

    /**
     * Answers the observation of a statement on one server, from a fresh fixture.
     *
     * @return array<string, mixed>
     */
    public function run(string $dsn, string $user, string $password, string $sql): array
    {
        $pdo = $this->connect($dsn, $user, $password);
        $observer = new Observer();
        $ordered = preg_match('/\border\s+by\b/i', $sql) === 1 && preg_match('/\blimit\b/i', $sql) !== 1;
        $observation = $observer->observe($pdo, $sql, $ordered);
        $observation['tables'] = $observer->tables($pdo, self::DATABASE);

        return $observation;
    }

    /**
     * Connects to a server and prepares the fixture database.
     */
    public function connect(string $dsn, string $user, string $password): PDO
    {
        try {
            $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $this->emulate]);
            $pdo->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
            $pdo->exec('CREATE DATABASE `' . self::DATABASE . '`');
            $pdo->exec('USE `' . self::DATABASE . '`');
            foreach ((new Fixture())->statements() as $statement) {
                $pdo->exec($statement);
            }
        } catch (PDOException $failure) {
            fwrite(STDERR, "Setup failed on {$dsn}: {$failure->getMessage()}\n");
            exit(2);
        }

        return $pdo;
    }
}
