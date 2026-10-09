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

    private ?PDO $guard = null;

    private ?PDO $memoryGuard = null;

    /**
     * The account the statements run as on the MySQL server, read before any statement ran.
     */
    private ?string $account = null;

    /**
     * @param string $native The PDO DSN of the MySQL server, without a database
     * @param string $nativeUser The user of the MySQL server
     * @param string $nativePassword The password of the MySQL server
     * @param string $memory The PDO DSN of mysql-memory, without a database
     * @param bool $emulate Whether PDO emulates prepared statements
     * @param string $version The MySQL release of both servers, as `8.4.7`
     * @param string|null $guardUser The account the MySQL server is repaired through, or null for the native user
     * @param bool $foundRows Whether the clients request CLIENT_FOUND_ROWS
     */
    public function __construct(
        public readonly string $native,
        public readonly string $nativeUser,
        public readonly string $nativePassword,
        public readonly string $memory,
        public readonly bool $emulate = true,
        public readonly string $version = '8.4.7',
        public readonly ?string $guardUser = null,
        public readonly bool $foundRows = false,
        public readonly ?Baseline $baseline = null,
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
        return $this->compare($sql)->difference;
    }

    /**
     * Compares one statement, distinguishing a skipped volatile observation from an equal one.
     */
    public function compare(string $sql): Comparison
    {
        $guard = $this->guard();
        $this->repair($guard);
        $this->baseline?->restore($guard, true);
        $expected = $this->run($this->native, $this->nativeUser, $this->nativePassword, $sql);
        $this->repair($guard);
        $this->baseline?->restore($guard, true);
        $again = $this->run($this->native, $this->nativeUser, $this->nativePassword, $sql);
        $this->repair($guard);
        $this->baseline?->restore($guard, true);
        if ($expected !== $again) {
            return new Comparison(true);
        }
        $this->repair($this->memoryGuard());
        $this->baseline?->restore($this->memoryGuard(), false);
        $actual = $this->run($this->memory, 'root', '', $sql);
        if ($expected === $actual) {
            return new Comparison(false);
        }
        $lines = [];
        foreach (array_unique([...array_keys($expected), ...array_keys($actual)]) as $key) {
            if (($expected[$key] ?? null) !== ($actual[$key] ?? null)) {
                $lines[] = $key . "\n  expected: " . $this->json($expected[$key] ?? null) . "\n  actual:   " . $this->json($actual[$key] ?? null);
            }
        }

        return new Comparison(false, implode("\n", $lines));
    }

    /**
     * Answers the connection that repairs the MySQL server after a statement, opened once before any statement runs, as the guard account.
     */
    public function guard(): PDO
    {
        if ($this->guard === null) {
            $statements = new PDO($this->native, $this->nativeUser, $this->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
            $account = $statements->query('SELECT CURRENT_USER()');
            $current = $account === false ? '' : $account->fetchColumn();
            $this->account = is_string($current) && $current !== '' ? $current : $this->nativeUser . '@%';
            $this->guard = new PDO($this->native, $this->guardUser ?? $this->nativeUser, $this->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
        }

        return $this->guard;
    }

    /**
     * Answers the connection that repairs mysql-memory before a statement, so that it starts from the state the MySQL server is repaired to.
     */
    public function memoryGuard(): PDO
    {
        return $this->memoryGuard ??= new PDO($this->memory, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
    }

    /**
     * Restores what a statement may have changed for the account and the server: the account the guard authenticated as, its password, TLS requirement, resource limits and privileges, and the global modes that refuse connections or writes.
     */
    public function repair(PDO $guard): void
    {
        $this->guard();
        [$user, $host] = explode('@', $this->account ?? $this->nativeUser . '@%', 2) + [1 => '%'];
        $quoted = $guard->quote($user) . '@' . $guard->quote($host);
        $password = $guard->quote($this->nativePassword);
        foreach ($this->repairs($quoted, $password) as $statement) {
            $guard->exec($statement);
        }
    }

    /**
     * Answers the statements that restore the account and the global modes on the release under test.
     *
     * MySQL 5.6 lacks CREATE USER IF NOT EXISTS, ALTER USER IDENTIFIED BY, roles, offline_mode and
     * super_read_only: GRANT creates the account there and SET PASSWORD restores its password.
     * MySQL 5.7 lacks roles and the redo log switch. A statement that disables the InnoDB redo
     * log would leave the MySQL server unable to restart, so every repair enables it again.
     *
     * @return list<string>
     */
    public function repairs(string $quoted, string $password): array
    {
        if (str_starts_with($this->version, '5.6.')) {
            return [
                "GRANT ALL ON *.* TO {$quoted} IDENTIFIED BY {$password} REQUIRE NONE WITH GRANT OPTION MAX_QUERIES_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_USER_CONNECTIONS 0",
                "SET PASSWORD FOR {$quoted} = PASSWORD({$password})",
                'SET GLOBAL read_only = OFF',
                'SET GLOBAL tx_read_only = OFF',
            ];
        }
        $statements = [
            "CREATE USER IF NOT EXISTS {$quoted} IDENTIFIED BY {$password}",
            "ALTER USER {$quoted} IDENTIFIED BY {$password} REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_USER_CONNECTIONS 0 ACCOUNT UNLOCK PASSWORD EXPIRE NEVER",
            "GRANT ALL ON *.* TO {$quoted} WITH GRANT OPTION",
            "SET DEFAULT ROLE NONE TO {$quoted}",
            'SET GLOBAL offline_mode = OFF',
            'SET GLOBAL super_read_only = OFF',
            'SET GLOBAL read_only = OFF',
            'SET GLOBAL transaction_read_only = OFF',
            'ALTER INSTANCE ENABLE INNODB REDO_LOG',
        ];

        return str_starts_with($this->version, '5.7.') ? array_values(array_diff($statements, ["SET DEFAULT ROLE NONE TO {$quoted}", 'ALTER INSTANCE ENABLE INNODB REDO_LOG'])) : $statements;
    }

    /**
     * Writes a value as compact JSON for a report.
     */
    public function json(mixed $value): string
    {
        $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return (string) $text;
    }

    /**
     * Answers the observation of a statement on one server, from a fresh fixture.
     * Ends its session explicitly after recording it: a PDO statement retained by an exception
     * can otherwise retain the connection and its server locks until garbage collection.
     *
     * @return array<string, mixed>
     */
    public function run(string $dsn, string $user, string $password, string $sql): array
    {
        $pdo = $this->connect($dsn, $user, $password);
        $observer = new Observer();
        $ordered = preg_match('/\border\s+by\b/i', $sql) === 1;
        $observation = $observer->observe($pdo, $sql, $ordered);
        $observation['tables'] = $observer->tables($pdo, self::DATABASE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $pdo->exec('ROLLBACK');
        $pdo->exec('KILL CONNECTION_ID()');
        $pdo = null;
        gc_collect_cycles();

        return $observation;
    }

    /**
     * Connects to a server and prepares the fixture database, writable again if a statement made it read-only.
     */
    public function connect(string $dsn, string $user, string $password): PDO
    {
        try {
            $foundRows = class_exists(\Pdo\Mysql::class) ? \Pdo\Mysql::ATTR_FOUND_ROWS : PDO::MYSQL_ATTR_FOUND_ROWS;
            $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $this->emulate, $foundRows => $this->foundRows]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            $pdo->exec('ALTER SCHEMA `' . self::DATABASE . '` READ ONLY = 0');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
