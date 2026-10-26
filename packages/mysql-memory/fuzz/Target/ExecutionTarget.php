<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use Fuzz\Fixture\Baseline;
use Fuzz\Fixture\Servers;
use Fuzz\Fixture\Tables;
use Fuzz\Observation\CatalogState;
use Fuzz\Observation\Comparison;
use Fuzz\Observation\LibraryErrors;
use Fuzz\Observation\MetadataLocks;
use Fuzz\Observation\Observer;
use Fuzz\Observation\OwnerThread;
use Fuzz\Observation\Process;
use Fuzz\Observation\RandomPasswords;
use Fuzz\Observation\Record;
use Fuzz\Observation\SessionState;
use Fuzz\Observation\StatementIdentities;
use Fuzz\Observation\Status;
use Fuzz\Observation\TableTimes;
use Fuzz\Observation\TemporaryFiles;
use PDO;
use PDOException;
use RuntimeException;
use SqlParser\Lexer\SourceException;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\SqlMode;

/**
 * Runs one statement on a MySQL server and on mysql-memory, from the same fixture, and requires every observation to be equal.
 *
 * The statement runs twice on the MySQL server, each time on a fresh database; when the two
 * observations differ after the bounded observation contracts, the statement is volatile (it reads the clock, a random number, or a
 * server identity) and fails the target. Otherwise the observation of mysql-memory must equal it:
 * the result columns and rows, or the error, the warnings, and the rows of every table after.
 */
final class ExecutionTarget
{
    /**
     * The database each input runs in.
     */
    public const DATABASE = 'fz';

    private ?PDO $guard = null;

    private ?PDO $memoryGuard = null;

    private ?string $input = null;

    private ?string $previousInput = null;

    /**
     * @var list<Record> Raw, possibly incomplete observations of the current input
     */
    public array $observations = [];

    private bool $usable = true;

    /**
     * The account the statements run as on the MySQL server, read before any statement ran.
     */
    private ?string $account = null;

    /** @var array<string, MySqlParser> Official grammar parsers for the exercised lexical profiles */
    private array $parsers = [];

    /**
     * @param string $native The PDO DSN of the MySQL server, without a database
     * @param string $nativeUser The user of the MySQL server
     * @param string $nativePassword The password of the MySQL server
     * @param string $memory The PDO DSN of mysql-memory, without a database
     * @param bool $emulate Whether PDO emulates prepared statements
     * @param string $version The MySQL release of both servers, as `8.4.7`
     * @param string|null $guardUser The account the MySQL server is repaired through, or null for the native user
     * @param bool $foundRows Whether the clients request CLIENT_FOUND_ROWS
     * @param \MySqlMemory\Server\MemoryDatabase|null $server The emulator process retained for the lifetime of this target
     * @param \Testcontainers\Containers\ContainerInstance|null $reference The owned reference container, or null for an external server
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
        public readonly ?\MySqlMemory\Server\MemoryDatabase $server = null,
        public readonly ?\Testcontainers\Containers\ContainerInstance $reference = null,
    ) {
    }

    /**
     * Requires matching results for one generated SQL input; every counterexample fails PHP-Fuzzer.
     *
     * @throws Error When the reference is unstable or the two servers disagree
     * @throws RuntimeException When an observation, fixture restoration or reuse check fails
     */
    public function verify(string $sql, string $input, string $mode = ''): void
    {
        $this->assertUsable();
        $this->observations = [];
        $verified = false;
        try {
            $action = $this->lifecycleAction($sql, $mode);
            if ($action === null) {
                $comparison = $this->compare($sql, $mode);
            } else {
                $lifecycle = new \Fuzz\Execution\Lifecycle();
                try {
                    $comparison = $lifecycle->compare($sql, $action, $this->version, $mode, $this->emulate);
                } finally {
                    $this->observations = $lifecycle->observations;
                }
            }
            if ($comparison->difference !== null || $comparison->volatile) {
                $message = $comparison->volatile ? 'MySQL reference observations are unstable.' : 'mysql-memory differs from MySQL.';
                throw new Error($message . "\nInput (hex): " . bin2hex($input) . "\nSQL mode: {$mode}\nSQL: {$sql}\n" . ($comparison->difference ?? $comparison->referenceDifference) . "\nRaw observations (base64 serialized): " . base64_encode(serialize($comparison->observations)));
            }
            $verified = true;
        } finally {
            if (!$verified) {
                fwrite(STDERR, "\nExecution failure evidence (base64 serialized): " . base64_encode(serialize(['input' => $input, 'sql' => $sql, 'mode' => $mode, 'version' => $this->version, 'driver' => $this->emulate ? 'text' : 'prepared', 'foundRows' => $this->foundRows, 'observations' => $this->observations])) . "\n");
            }
        }
    }

    /**
     * Selects the lifecycle boundary independently of the semantic implementation.
     * A grammar refusal still reaches ordinary execution and comparison.
     */
    public function lifecycleAction(string $sql, string $mode = ''): ?\Fuzz\Execution\LifecycleAction
    {
        $parser = $this->parsers[$mode] ??= new MySqlParser('mysql-' . $this->version, SqlMode::fromString($mode));
        try {
            return \Fuzz\Execution\LifecycleAction::read($parser->parse($sql));
        } catch (SourceException) {
            return null;
        }
    }

    /**
     * Compares one statement, distinguishing an unstable reference observation from an equal one.
     * Repairs the emulator after observing it too, so later clients cannot inherit expired credentials or server restrictions.
     *
     * @throws RuntimeException When an observation or fixture restoration fails
     */
    public function compare(string $sql, string $mode = ''): Comparison
    {
        $this->assertUsable();
        $this->observations = [];
        $this->previousInput = $this->input;
        $this->input = $sql;
        $first = $this->run($this->native, $this->nativeUser, $this->nativePassword, $sql, $mode);
        $expected = $first['comparable'];
        $second = $this->run($this->native, $this->nativeUser, $this->nativePassword, $sql, $mode);
        $again = $second['comparable'];
        $observations = ['native' => $first['raw'], 'repeated' => $second['raw']];
        $library = new LibraryErrors();
        $files = new TemporaryFiles();
        $normalized = $library->comparable($expected, $this->version);
        $recordedContracts = $expected['contracts'] ?? null;
        $contracts = is_array($recordedContracts) ? array_values(array_filter($recordedContracts, 'is_string')) : [];
        $contracts = [...$contracts, ...($normalized === $expected ? [] : [LibraryErrors::CONTRACT])];
        $fileNormalized = $files->comparable($normalized, $this->version);
        $contracts = [...$contracts, ...($fileNormalized === $normalized ? [] : [TemporaryFiles::CONTRACT])];
        $expected = $fileNormalized;
        $again = $files->comparable($library->comparable($again, $this->version), $this->version);
        if ($expected !== $again) {
            return new Comparison(true, contracts: $contracts, referenceDifference: $this->describe($expected, $again), observations: $observations);
        }
        $memory = $this->run($this->memory, 'root', '', $sql, $mode);
        $observations['memory'] = $memory['raw'];
        $actual = $files->comparable($library->comparable($memory['comparable'], $this->version), $this->version);
        if ($expected === $actual) {
            return new Comparison(false, contracts: $contracts, observations: $observations);
        }
        return new Comparison(false, $this->describe($expected, $actual), $contracts, observations: $observations);
    }

    /**
     * Describes only the differing observation fields, including differences between native runs.
     *
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    public function describe(array $expected, array $actual): string
    {
        $lines = [];
        foreach (array_unique([...array_keys($expected), ...array_keys($actual)]) as $key) {
            if (($expected[$key] ?? null) !== ($actual[$key] ?? null)) {
                $lines[] = $key . "\n  expected: " . $this->json($expected[$key] ?? null) . "\n  actual:   " . $this->json($actual[$key] ?? null);
            }
        }

        return implode("\n", $lines);
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
        return $this->memoryGuard ??= new PDO($this->memory, $this->guardUser ?? $this->nativeUser, '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
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
            (new Tables())->execute($guard, $statement);
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
            "ALTER USER {$quoted} DISCARD OLD PASSWORD",
            "GRANT ALL ON *.* TO {$quoted} WITH GRANT OPTION",
            "SET DEFAULT ROLE NONE TO {$quoted}",
            'SET GLOBAL offline_mode = OFF',
            'SET GLOBAL super_read_only = OFF',
            'SET GLOBAL read_only = OFF',
            'SET GLOBAL transaction_read_only = OFF',
            'ALTER INSTANCE ENABLE INNODB REDO_LOG',
        ];

        return str_starts_with($this->version, '5.7.') ? array_values(array_diff($statements, ["SET DEFAULT ROLE NONE TO {$quoted}", 'ALTER INSTANCE ENABLE INNODB REDO_LOG', "ALTER USER {$quoted} DISCARD OLD PASSWORD"])) : $statements;
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
     * Isolated runs restore their original configuration before returning, including direct callers.
     *
     * @return array{raw: array<string, mixed>, comparable: array<string, mixed>}
     * @throws RuntimeException When setup, observation or restoration fails
     */
    public function run(string $dsn, string $user, string $password, string $sql, string $mode = ''): array
    {
        $this->assertUsable();
        $record = new Record($dsn === $this->native ? 'native' : 'memory', $sql, $mode);
        $this->observations[] = $record;
        $pdo = null;
        $connection = null;
        try {
            $guard = $dsn === $this->native ? $this->guard() : $this->memoryGuard();
            $this->repair($guard);
            $this->baseline?->restore($guard);
            $record->phase = 'connection';
            $pdo = $this->connect($dsn, $user, $password);
            $connection = (new OwnerThread())->connection($pdo);
            $record->phase = 'fixture setup';
            $clock = TableTimes::handles($sql, $this->version) ? new TableTimes($this->version) : null;
            $this->prepare($pdo, $clock);
            $observation = $this->observe($pdo, $dsn, $sql, $mode, $record);
            $record->phase = 'comparison contracts';
            $result = ['raw' => $observation['raw'], 'comparable' => $clock?->comparable($observation['comparable']) ?? $observation['comparable']];
            $record->phase = 'complete';
            return $result;
        } finally {
            try {
                if ($connection !== null) {
                    $this->restore($dsn, $connection, $record);
                }
            } finally {
                $pdo = null;
                gc_collect_cycles();
                if (!$record->restored) {
                    $this->usable = false;
                }
            }
        }
    }

    /**
     * Ends an input's session before restoring the server's requested baseline.
     *
     * @param positive-int $connection The identity captured before fixture setup
     * @throws RuntimeException When termination, integrity or fixture restoration fails
     */
    public function restore(string $dsn, int $connection, Record $record): void
    {
        $record->cleanup = 'disconnect';
        $guard = $dsn === $this->native ? $this->guard() : $this->memoryGuard();
        try {
            (new \Fuzz\Fixture\Connections())->release($guard, $connection);
            $record->cleanup = 'server integrity';
        } finally {
            if ($dsn === $this->memory) {
                $this->server?->assertNoInternalFailure();
            }
        }
        $record->cleanup = 'fixture restoration';
        $this->repair($guard);
        $this->baseline?->restore($guard);
        $record->restored = true;
        $record->cleanup = 'complete';
    }

    /**
     * Refuses any further input after an incomplete fixture restoration.
     *
     * @throws RuntimeException When a fresh target and owned fixture are required
     */
    public function assertUsable(): void
    {
        if (!$this->usable) {
            throw new RuntimeException('The differential fixture is unusable after an incomplete restoration; start a new target.');
        }
    }

    /**
     * Captures raw results and the comparison copy before the owning run restores its fixture.
     *
     * @return array{raw: array<string, mixed>, comparable: array<string, mixed>}
     */
    public function observe(PDO $pdo, string $dsn, string $sql, string $mode, ?Record $record = null): array
    {
        $record ??= new Record($dsn === $this->native ? 'native' : 'memory', $sql, $mode);
        $record->phase = 'observation setup';
        if ($mode !== '') {
            (new Tables())->execute($pdo, "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, " . $pdo->quote($mode) . ')');
        }
        if ($this->baseline !== null) {
            Baseline::mutexes($pdo, $this->version);
        }
        $guard = $dsn === $this->native ? $this->guard() : $this->memoryGuard();
        $owner = null;
        if (!str_starts_with($this->version, '5.6.')) {
            $owner = (new OwnerThread())->connection($pdo);
            (new MetadataLocks())->prepare($guard, $owner);
        }
        $passwords = RandomPasswords::handles($sql, $this->version) ? RandomPasswords::capture($pdo, preg_match('/\bRETAIN\s+CURRENT\s+PASSWORD\b/i', $sql) === 1) : null;
        $identities = StatementIdentities::handles($sql, $this->version) ? StatementIdentities::capture($pdo, $this->version) : null;
        if (Process\Listing::handles($sql)) {
            Process\Listing::prepare($pdo);
        }
        $processes = Process\Listing::handles($sql) ? Process\Listing::capture($pdo, $dsn === $this->native ? $this->guard() : $this->memoryGuard()) : null;
        if (Status\Listing::handles($sql)) {
            /** Reset the connection maximum to current clients on both servers before capturing status. */
            (new Tables())->execute($pdo, 'FLUSH STATUS');
        }
        $status = Status\Listing::handles($sql) ? Status\Listing::capture($pdo, $sql) : null;
        $observer = new Observer();
        $parser = $this->parsers[$mode] ??= new MySqlParser('mysql-' . $this->version, SqlMode::fromString($mode));
        try {
            $ordered = (new \Fuzz\Observation\ResultOrder())->ordered($parser->parse($sql));
        } catch (SourceException) {
            // A grammar refusal is still executed and compared; it cannot justify reordering a result.
            $ordered = true;
        }
        $observation = $observer->observe($pdo, $sql, true, $record);
        if ($dsn === $this->memory) {
            $this->server?->assertNoInternalFailure();
        }
        $raw = &$record->raw;
        if ($owner !== null) {
            $record->phase = 'metadata locks';
            $locks = (new MetadataLocks())->capture($guard, $owner, $this->version);
            $raw['metadataLocks'] = $locks['raw'];
            $observation['metadataLocks'] = $locks['comparable'];
        }
        $record->phase = 'session state';
        $state = (new SessionState())->capture($pdo, $this->version);
        $raw['session'] = $state['raw'];
        $observation['session'] = $state['comparable'];
        if (!str_starts_with($this->version, '5.6.')) {
            $record->phase = 'user variables';
            $userVariables = (new \Fuzz\Observation\UserVariables())->capture($pdo, $guard);
            $raw['session']['userVariables'] = $userVariables['raw'];
            $observation['session']['userVariables'] = $userVariables['comparable'];
        }
        $record->phase = 'global variables';
        $observation['globals'] = (new Servers())->globals($guard, $this->version);
        $raw['globals'] = $observation['globals'];
        ksort($observation['globals']);
        $record->phase = 'catalog state';
        $catalog = (new CatalogState())->capture($guard, $this->version);
        $raw['catalog'] = $catalog['raw'];
        $observation['catalog'] = $catalog['comparable'];
        $record->phase = 'comparison contracts';
        if (!$ordered && isset($observation['results']) && is_array($observation['results'])) {
            foreach ($observation['results'] as $index => $result) {
                if (is_array($result) && isset($result['rows']) && is_array($result['rows'])) {
                    $result['rows'] = $observer->bag($result['rows']);
                    $observation['results'][$index] = $result;
                }
            }
        }
        $observation = $identities?->comparable($pdo, $observation) ?? $observation;
        $observation = $processes?->comparable($pdo, $observation) ?? $observation;
        $observation = $status?->comparable($observation) ?? $observation;
        $observation = (new \Fuzz\Observation\EngineReports())->comparable($sql, $observation);
        $record->phase = 'owner-visible tables';
        $observation['tables'] = $observer->tables($pdo, self::DATABASE);
        $raw['tables'] = $observation['tables'];
        $record->phase = 'owner-visible definitions';
        $observation['definitions'] = $observer->definitions($pdo, self::DATABASE);
        $raw['definitions'] = $observation['definitions'];
        $record->phase = 'password contracts';
        $observation = $passwords?->comparable($pdo, $observation) ?? $observation;
        return ['raw' => $raw, 'comparable' => $observation];
    }

    /**
     * Opens a connection whose lifetime is owned by run(), including failed setup.
     *
     * @throws RuntimeException When the fixture cannot be prepared; the input remains a failed observation
     */
    public function connect(string $dsn, string $user, string $password): PDO
    {
        try {
            $foundRows = defined('Pdo\\Mysql::ATTR_FOUND_ROWS') ? constant('Pdo\\Mysql::ATTR_FOUND_ROWS') : PDO::MYSQL_ATTR_FOUND_ROWS;
            $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $this->emulate, $foundRows => $this->foundRows]);

        } catch (PDOException $failure) {
            throw new RuntimeException("Setup failed on {$dsn}: {$failure->getMessage()}\nCurrent input: " . $this->json($this->input) . "\nPrevious input: " . $this->json($this->previousInput) . "\n", 0, $failure);
        }

        return $pdo;
    }
    /**
     * Prepares the same table fixture before the generated input, on an owned connection.
     */
    public function prepare(PDO $pdo, ?TableTimes $clock = null): void
    {
        (new Tables())->remove($pdo, self::DATABASE, $this->version);
        $pdo->exec('CREATE DATABASE `' . self::DATABASE . '`');
        $pdo->exec('USE `' . self::DATABASE . '`');
        foreach ((new Tables())->statements() as $statement) {
            if ($clock === null) {
                (new Tables())->execute($pdo, $statement);
            } else {
                $clock->execute($pdo, $statement);
            }
        }
        $this->baseline?->logs($pdo);
    }

}
