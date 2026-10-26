<?php

declare(strict_types=1);

namespace Fuzz\Fixture;

use Container\Endpoint;
use Container\MySqlRelease;
use Fuzz\Container\DisposableMySql;
use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use Fuzz\Target\ExecutionTarget;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use RuntimeException;
use Testcontainers\Testcontainers;

/**
 * Starts the two servers of a differential run: a MySQL server of the release under test, and mysql-memory emulating it.
 *
 * MYSQL_VERSION names the release (default 8.4.7). MYSQL_MEMORY_NATIVE_DSN, with
 * MYSQL_MEMORY_NATIVE_USER and MYSQL_MEMORY_NATIVE_PASSWORD, names a running server to use
 * instead of a container. mysql-memory starts with the global variables of the MySQL server, so
 * both report the same host name, paths and identities.
 */
final class Servers
{
    /**
     * The account the differential target repairs the servers through.
     */
    public const GUARD = 'memory_guard';

    /**
     * Starts both servers and answers the differential target over them, and the grammar of the release.
     *
     * @return array{ExecutionTarget, string, MemoryDatabase}
     * @throws RuntimeException When initialization fails, retaining its observations after disposing owned servers
     */
    public function start(bool $emulate = true, bool $isolate = false): array
    {
        $version = getenv('MYSQL_VERSION') !== false ? (string) getenv('MYSQL_VERSION') : MySqlRelease::DEFAULT;
        $dsn = getenv('MYSQL_MEMORY_NATIVE_DSN');
        $reference = null;
        $server = null;
        $record = new Record('startup', '', '');
        $record->phase = 'native startup';
        try {
            try {
                if ($dsn !== false && $dsn !== '') {
                    $user = $this->environment('MYSQL_MEMORY_NATIVE_USER', 'root');
                    $password = $this->environment('MYSQL_MEMORY_NATIVE_PASSWORD', 'root');
                } else {
                    $reference = Testcontainers::run($this->reference($version));
                    $endpoint = $reference->getData(Endpoint::class);
                    $dsn = 'mysql:host=' . $endpoint->host . ';port=' . $endpoint->port;
                    $user = $endpoint->username;
                    $password = $endpoint->password;
                }
                $native = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $this->assertVersion($native, $version);
                $record->phase = 'native initialization';
                $initial = new Record('native', '', '');
                $record->probes[] = $initial;
                $this->clean($native, $version, $initial);
                $globals = $this->globals($native, $version, $initial);
                $peer = $this->peer($native, $initial);
                $routines = $this->routineTimestamps($native, $initial);
                $priorities = $this->resourcePriorities($native, $version, $initial);
                $processors = $this->resourceProcessors($native, $version, $initial);
                $help = $this->help($native, $initial);
                $zones = $this->timeZones($native, $initial);
                $this->guard($native, $version, $password, $initial);
                $baseline = $isolate ? new Baseline($native, $version, $initial) : null;
                $initial->phase = 'complete';
                $record->phase = 'memory startup';
                $server = MemoryDatabase::start($version, [], $globals, $peer, routineTimestamps: $routines, resourcePriorities: $priorities, resourceProcessors: $processors, help: $help, timeZones: $zones);
                $record->phase = 'memory initialization';
                $memory = new Record('memory', '', '');
                $record->probes[] = $memory;
                $this->guard(new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), $version, $password, $memory);
                $server->assertNoInternalFailure();
                $memory->phase = 'complete';
                $target = new ExecutionTarget($dsn, $user, $password, $server->dsn(), $emulate, $version, self::GUARD, getenv('MYSQL_MEMORY_FOUND_ROWS') === '1', $baseline, $server, $reference, $record);
                $record->phase = 'complete';
                $record->cleanup = 'ownership transferred';
                return [$target, 'mysql-' . $version, $server];
            } finally {
                if ($record->phase !== 'complete') {
                    try {
                        $record->cleanup = 'memory server';
                        try {
                            $server?->assertNoInternalFailure();
                        } finally {
                            $server?->stop();
                        }
                    } finally {
                        $record->cleanup = 'native server';
                        $reference?->stop();
                    }
                    $record->cleanup = 'complete';
                }
            }
        } catch (RuntimeException $failure) {
            throw new RuntimeException('Differential fixture initialization failed: ' . $failure->getMessage() . "\nEvidence (base64 serialized): " . base64_encode(serialize($record)), 0, $failure);
        }
    }

    /**
     * Requires the connection's advertised release to match the selected grammar.
     * Packaging suffixes do not change the release. Validate before modifying
     * the native fixture or importing its configuration into the memory server.
     *
     * @throws RuntimeException When the reference release is unavailable or mismatched
     */
    public function assertVersion(PDO $connection, string $version): void
    {
        $actual = $connection->getAttribute(PDO::ATTR_SERVER_VERSION);
        if (!is_string($actual) || explode('-', $actual, 2)[0] !== $version) {
            throw new RuntimeException('The native MySQL release does not match the grammar: expected ' . $version . ', received ' . var_export($actual, true) . '.');
        }
    }

    /**
     * Owns a fresh reference container, using the repository's pinned release image.
     *
     * A failed reset must not contaminate a later campaign or another test's server.
     * DisposableMySql also applies the harness's optional fixed port.
     *
     * @throws RuntimeException When no pinned image exists for the requested release
     */
    public function reference(string $version): \Container\MySqlContainer
    {
        return new DisposableMySql($version);
    }

    /**
     * Shares the installed tzdata names and identifiers as initial state, before generated SQL runs.
     * Conversion and temporal arithmetic still execute independently in the two engines.
     *
     * @return list<array{string, int, bool}>
     * @throws RuntimeException When a time-zone identity or setting is invalid
     */
    public function timeZones(PDO $native, ?Record $record = null): array
    {
        $zones = [];
        foreach ($this->rows($native, 'SELECT n.Name, n.Time_zone_id, z.Use_leap_seconds FROM mysql.time_zone_name n JOIN mysql.time_zone z USING (Time_zone_id) ORDER BY n.Time_zone_id', $record) as $row) {
            if (count($row) !== 3 || $row[0] === '' || !ctype_digit($row[1]) || !in_array($row[2], ['Y', 'N'], true)) {
                throw new RuntimeException('The time-zone fixture contains a malformed name, identity or leap-second setting.');
            }
            $id = filter_var($row[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!is_int($id)) {
                throw new RuntimeException('The time-zone fixture identity is outside the positive integer domain.');
            }
            $zones[] = [$row[0], $id, $row[2] === 'Y'];
        }
        return $zones;
    }

    /**
     * Captures installed help data as an initial fixture, before any generated SQL runs.
     *
     * HELP output is not imported or exempted from comparison. Search, precedence, sorting
     * and result metadata are computed independently over these rows by the memory server.
     * Text is read as UTF-8 bytes independently of the initial client character set.
     * @return array<string, list<list<string>>>
     */
    public function help(PDO $native, ?Record $record = null): array
    {
        return [
            'topics' => $this->rows($native, 'SELECT help_topic_id, CAST(CONVERT(name USING utf8mb4) AS BINARY), help_category_id, CAST(CONVERT(description USING utf8mb4) AS BINARY), CAST(CONVERT(example USING utf8mb4) AS BINARY) FROM mysql.help_topic', $record),
            'categories' => $this->rows($native, 'SELECT help_category_id, CAST(CONVERT(name USING utf8mb4) AS BINARY), parent_category_id FROM mysql.help_category', $record),
            'keywords' => $this->rows($native, 'SELECT help_keyword_id, CAST(CONVERT(name USING utf8mb4) AS BINARY) FROM mysql.help_keyword', $record),
            'relations' => $this->rows($native, 'SELECT help_topic_id, help_keyword_id FROM mysql.help_relation', $record),
        ];
    }

    /**
     * Captures startup configuration, preserving NULL reads that SHOW VARIABLES renders as empty strings.
     *
     * @return array<string, string|null>
     * @throws RuntimeException When the initial configuration cannot be read completely
     */
    public function globals(PDO $native, string $version, ?Record $record = null): array
    {
        $globals = [];
        $empty = [];
        $observer = new Observer();
        foreach ($observer->rows($native, str_starts_with($version, '5.6.') ? 'SHOW GLOBAL VARIABLES' : 'SELECT VARIABLE_NAME, VARIABLE_VALUE FROM performance_schema.global_variables', $record) as $row) {
            if (!is_array($row) || count($row) !== 2 || !is_string($row[0] ?? null) || $row[0] === '' || array_key_exists(strtolower($row[0]), $globals) || (!is_string($row[1]) && $row[1] !== null)) {
                throw new RuntimeException('The global variable inventory returned an invalid row.');
            }
            [$name, $value] = $row;
            $globals[strtolower($name)] = $value;
            if ($value === '') {
                $empty[] = strtolower($name);
            }
        }
        if ($globals === []) {
            throw new RuntimeException('The global variable inventory is empty.');
        }
        if ($empty !== []) {
            $rows = $observer->rows($native, 'SELECT ' . implode(', ', array_map(static fn (string $name): string => '@@GLOBAL.`' . str_replace('`', '``', $name) . '`', $empty)), $record);
            $values = count($rows) === 1 ? $rows[0] : null;
            if (!is_array($values) || count($values) !== count($empty)) {
                throw new RuntimeException('Cannot distinguish NULL global fixture variables from empty strings.');
            }
            foreach ($empty as $position => $emptyName) {
                if ($values[$position] === null) {
                    $globals[$emptyName] = null;
                }
            }
        }

        return $globals;
    }

    /**
     * Creates the account the differential target repairs the servers through, on one server.
     *
     * The account has every privilege, and no generated statement names it, so a statement that
     * drops or locks the account the statements run as cannot take the repair down with it. It is
     * created on both servers, so that both list the same accounts. PROXY on CURRENT_USER stores
     * the empty wildcard mapping, allowing the guard to remove generated PROXY grants too.
     */
    public function guard(PDO $connection, string $version, string $password, ?Record $record = null): void
    {
        $account = "'" . self::GUARD . "'@'%'";
        $quoted = $connection->quote($password);
        $statements = str_starts_with($version, '5.6.')
            ? ["GRANT ALL ON *.* TO {$account} IDENTIFIED BY {$quoted} WITH GRANT OPTION"]
            : ["CREATE USER IF NOT EXISTS {$account} IDENTIFIED BY {$quoted}", "GRANT ALL ON *.* TO {$account} WITH GRANT OPTION"];
        $statements[] = "GRANT PROXY ON CURRENT_USER TO {$account} WITH GRANT OPTION";
        foreach ($statements as $statement) {
            (new Tables())->execute($connection, $statement, $record);
        }
    }

    /**
     * Removes what statements of earlier runs left on the MySQL server outside the fixture database.
     *
     * Accounts and roles other than root and the server's own, foreign servers, resource groups
     * other than the defaults, and the database, table and column grants of root are dropped,
     * so that the MySQL server starts a run in the state mysql-memory starts in. MySQL 5.6 lacks
     * DROP USER IF EXISTS; the accounts it drops are those it lists, so it drops them without.
     * The root@% fixture starts without PROXY grants too; image-specific initial proxy grants
     * otherwise require privileges that the repair account does not hold.
     */
    public function clean(PDO $native, string $version = MySqlRelease::DEFAULT, ?Record $record = null): void
    {
        $statements = [];
        foreach ($this->rows($native, 'SELECT User, Host FROM mysql.user', $record) as [$name, $host]) {
            if (!in_array($name, ['root', self::GUARD, 'healthchecker', 'mysql.infoschema', 'mysql.session', 'mysql.sys'], true)) {
                $statements[] = (str_starts_with($version, '5.6.') ? 'DROP USER ' : 'DROP USER IF EXISTS ') . $native->quote($name) . '@' . $native->quote($host);
            }
        }
        foreach ($this->rows($native, 'SELECT Server_name FROM mysql.servers', $record) as [$name]) {
            $statements[] = 'DROP SERVER IF EXISTS `' . str_replace('`', '``', $name) . '`';
        }
        foreach (str_starts_with($version, '5.') ? [] : $this->rows($native, 'SELECT RESOURCE_GROUP_NAME FROM information_schema.RESOURCE_GROUPS', $record) as [$name]) {
            if (!in_array($name, ['SYS_default', 'USR_default'], true)) {
                $statements[] = 'DROP RESOURCE GROUP `' . str_replace('`', '``', $name) . '` FORCE';
            }
        }
        foreach (['db' => 'Db', 'tables_priv' => 'Db', 'columns_priv' => 'Db', 'procs_priv' => 'Db'] as $table => $column) {
            if ($this->rows($native, "SELECT 1 FROM mysql.{$table} WHERE User = 'root'", $record) !== []) {
                $statements[] = "DELETE FROM mysql.{$table} WHERE User = 'root'";
            }
        }
        $statements[] = "DELETE FROM mysql.proxies_priv WHERE User = 'root' AND Host = '%'";
        $statements[] = 'FLUSH PRIVILEGES';
        foreach ($statements as $statement) {
            (new Tables())->execute($native, $statement, $record);
        }
    }

    /**
     * Reads a non-null textual fixture interface, retaining the unconverted driver evidence.
     * Integer identifiers are rendered as decimal strings; NULL and other value types are rejected.
     *
     * @return list<list<string>>
     * @throws RuntimeException When a fixture query fails or returns an invalid row
     */
    public function rows(PDO $native, string $sql, ?Record $record = null): array
    {
        try {
            $observed = (new Observer())->rows($native, $sql, $record);
        } catch (RuntimeException $failure) {
            throw new RuntimeException('Cannot read a differential fixture: ' . $sql, 0, $failure);
        }
        $rows = [];
        foreach ($observed as $row) {
            if (!is_array($row) || !array_is_list($row) || $row === []) {
                throw new RuntimeException('The differential fixture returned an invalid row.');
            }
            $values = [];
            foreach ($row as $value) {
                if (!is_string($value) && !is_int($value)) {
                    throw new RuntimeException('The differential fixture requires a non-null text or integer cell: ' . $sql);
                }
                $values[] = (string) $value;
            }
            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * Reads the authenticated account without inferring its host from connection settings.
     * User names can contain @; the final separator precedes the matched host.
     *
     * @return array{string, non-empty-string}
     * @throws RuntimeException When the authenticated account cannot be read unambiguously
     */
    public function principal(PDO $connection, ?Record $record = null): array
    {
        $rows = $this->rows($connection, 'SELECT CURRENT_USER()', $record);
        $value = count($rows) === 1 && count($rows[0]) === 1 ? $rows[0][0] : '';
        $separator = strrpos($value, '@');
        if ($separator === false || $separator === strlen($value) - 1) {
            throw new RuntimeException('Cannot read the authenticated fixture account and matched host.');
        }
        $host = substr($value, $separator + 1);
        if ($host === '') {
            throw new RuntimeException('The authenticated fixture account has no host.');
        }
        return [substr($value, 0, $separator), $host];
    }

    /**
     * Reads the client's observed peer host independently of its matched grant-table host.
     *
     * @throws RuntimeException When USER() does not expose one client host
     */
    public function peer(PDO $connection, ?Record $record = null): string
    {
        $rows = $this->rows($connection, 'SELECT USER()', $record);
        $value = count($rows) === 1 && count($rows[0]) === 1 ? $rows[0][0] : '';
        $separator = strrpos($value, '@');
        if ($separator === false || $separator === strlen($value) - 1) {
            throw new RuntimeException('Cannot read the fixture client host.');
        }
        return substr($value, $separator + 1);
    }

    /**
     * Answers an environment variable, or the fallback when it is unset or empty.
     */
    public function environment(string $name, string $fallback): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * Captures installation dates before generated inputs run; routine signatures and attributes remain independently modeled.
     *
     * No routine implementation, names to install, or result rows are imported. The keys only
     * identify which independently installed entry receives each initial timestamp pair.
     *
     * @return array<string, array{int, int}>
     * @throws RuntimeException When an installation timestamp cannot be captured without loss
     */
    public function routineTimestamps(PDO $native, ?Record $record = null): array
    {
        $timestamps = [];
        foreach ($this->rows($native, "SELECT CONCAT(ROUTINE_TYPE, ':', ROUTINE_NAME), UNIX_TIMESTAMP(CREATED), UNIX_TIMESTAMP(LAST_ALTERED) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='sys'", $record) as $row) {
            if (count($row) !== 3 || $row[0] === '' || array_key_exists($row[0], $timestamps)) {
                throw new RuntimeException('The routine timestamp fixture contains an invalid or duplicate identity.');
            }
            $created = filter_var($row[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $modified = filter_var($row[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if (!is_int($created) || !is_int($modified)) {
                throw new RuntimeException('The routine timestamp fixture contains an invalid instant.');
            }
            $timestamps[$row[0]] = [$created, $modified];
        }

        return $timestamps;
    }
    /**
     * Samples the platform capability with an isolated group before the differential fixture.
     *
     * The probe is independent of generated SQL and leaves no resource group behind.
     * @throws RuntimeException When the server returns an unexpected priority value
     */
    public function resourcePriorities(PDO $native, string $version, ?Record $record = null): bool
    {
        if (str_starts_with($version, '5.')) {
            return true;
        }
        $name = 'memory_priority_' . bin2hex(random_bytes(8));
        (new Tables())->execute($native, 'CREATE RESOURCE GROUP ' . $name . ' TYPE USER THREAD_PRIORITY 1', $record);
        try {
            $rows = $this->rows($native, "SELECT THREAD_PRIORITY FROM information_schema.RESOURCE_GROUPS WHERE RESOURCE_GROUP_NAME = '" . $name . "'", $record);
            $priority = count($rows) === 1 && count($rows[0]) === 1 ? $rows[0][0] : null;
            if (!in_array($priority, ['0', '1'], true)) {
                throw new RuntimeException('The resource-group priority probe returned no supported capability value.');
            }

            return $priority === '1';
        } finally {
            (new Tables())->execute($native, 'DROP RESOURCE GROUP ' . $name, $record);
        }
    }

    /**
     * Reads the default group's processor domain before generated statements run.
     *
     * The fixture shares this platform capability; CPU validation errors are still compared exactly.
     * @return positive-int
     * @throws RuntimeException When the native default group does not describe a contiguous CPU domain
     */
    public function resourceProcessors(PDO $native, string $version, ?Record $record = null): int
    {
        if (str_starts_with($version, '5.')) {
            return 8;
        }
        $rows = $this->rows($native, "SELECT VCPU_IDS FROM information_schema.RESOURCE_GROUPS WHERE RESOURCE_GROUP_NAME = 'USR_default'", $record);
        $cpus = count($rows) === 1 && count($rows[0]) === 1 ? $rows[0][0] : null;
        if (!is_string($cpus) || preg_match('/\A0(?:-([0-9]+))?\z/', $cpus, $match) !== 1) {
            throw new RuntimeException('The default resource group did not expose a contiguous processor domain.');
        }
        $last = filter_var($match[1] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX - 1]]);
        if ($last === false) {
            throw new RuntimeException('The default resource group exposed an invalid processor index.');
        }

        return $last + 1;
    }

}
