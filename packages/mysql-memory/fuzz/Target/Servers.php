<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Container\Endpoint;
use Container\MySqlRelease;
use MySqlMemory\Server\Server;
use PDO;
use PDOException;
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
     * @var array{Differential, string, Server}|null The pair kept alive for regression cases in this process
     */
    private static ?array $shared = null;

    /**
     * Answers one pair for regression cases in the current process, retaining the emulator until process shutdown.
     *
     * @return array{Differential, string, Server}
     */
    public static function shared(): array
    {
        return self::$shared ??= (new self())->start();
    }

    /**
     * Releases a failed shared pair so the next independent test initializes a new pair.
     * The failed input still raises an error; it is never retried or counted as a match.
     * An owned reference is stopped before reuse can observe a container still exiting after a crash.
     * External reference servers have no owned handle and remain under their caller's control.
     */
    public static function discard(Differential $target): void
    {
        if (self::$shared !== null && self::$shared[0] === $target) {
            self::$shared[2]->stop();
            $target->reference?->stop();
            self::$shared = null;
        }
    }

    /**
     * The account the differential target repairs the servers through.
     */
    public const GUARD = 'memory_guard';

    /**
     * Starts both servers and answers the differential target over them, and the grammar of the release.
     *
     * @return array{Differential, string, Server}
     */
    public function start(bool $emulate = true, bool $isolate = false): array
    {
        $version = getenv('MYSQL_VERSION') !== false ? (string) getenv('MYSQL_VERSION') : MySqlRelease::DEFAULT;
        $dsn = getenv('MYSQL_MEMORY_NATIVE_DSN');
        $reference = null;
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
        $this->clean($native, $version);
        $globals = $this->globals($native, $version);
        $identity = $native->query('SELECT USER()');
        $account = $identity === false ? '' : $identity->fetchColumn();
        $account = is_string($account) ? $account : '';
        $server = Server::start($version, [], $globals, substr($account, (int) strrpos($account, '@') + 1), routineTimestamps: $this->routineTimestamps($native), resourcePriorities: $this->resourcePriorities($native, $version), resourceProcessors: $this->resourceProcessors($native, $version), help: $this->help($native), namedTimeZones: $this->namedTimeZones($native));
        $this->guard($native, $version, $password);
        $this->guard(new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), $version, $password);

        return [new Differential($dsn, $user, $password, $server->dsn(), $emulate, $version, self::GUARD, getenv('MYSQL_MEMORY_FOUND_ROWS') === '1', $isolate ? new Baseline($native, $version) : null, $server, $reference), 'mysql-' . $version, $server];
    }

    /**
     * Applies the harness's optional fixed port to the repository's reusable reference container.
     *
     * @throws RuntimeException When no pinned image exists for the requested release
     */
    public function reference(string $version): \Container\MySqlContainer
    {
        $definition = MySqlRelease::container($version);
        $container = new $definition();
        $port = filter_var(getenv('MYSQL_MEMORY_CONTAINER_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (is_int($port)) {
            $container->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port));
        }
        return $container;
    }

    /**
     * Captures whether the reference initialized its named time-zone tables.
     */
    public function namedTimeZones(PDO $native): bool
    {
        $statement = $native->query('SELECT COUNT(*) FROM mysql.time_zone_name');
        return $statement !== false && (int) $statement->fetchColumn() > 0;
    }

    /**
     * Captures installed help data as an initial fixture, before any generated SQL runs.
     *
     * HELP output is not imported or exempted from comparison. Search, precedence, sorting
     * and result metadata are computed independently over these rows by the memory server.
     * Text is read as UTF-8 bytes independently of the initial client character set.
     * @return array<string, list<list<string>>>
     */
    public function help(PDO $native): array
    {
        return [
            'topics' => $this->rows($native, 'SELECT help_topic_id, CAST(CONVERT(name USING utf8mb4) AS BINARY), help_category_id, CAST(CONVERT(description USING utf8mb4) AS BINARY), CAST(CONVERT(example USING utf8mb4) AS BINARY) FROM mysql.help_topic'),
            'categories' => $this->rows($native, 'SELECT help_category_id, CAST(CONVERT(name USING utf8mb4) AS BINARY), parent_category_id FROM mysql.help_category'),
            'keywords' => $this->rows($native, 'SELECT help_keyword_id, CAST(CONVERT(name USING utf8mb4) AS BINARY) FROM mysql.help_keyword'),
            'relations' => $this->rows($native, 'SELECT help_topic_id, help_keyword_id FROM mysql.help_relation'),
        ];
    }

    /**
     * Captures startup configuration, preserving NULL reads that SHOW VARIABLES renders as empty strings.
     *
     * @return array<string, string|null>
     */
    public function globals(PDO $native, string $version): array
    {
        $globals = [];
        $empty = [];
        foreach ($this->rows($native, str_starts_with($version, '5.6.') ? 'SHOW GLOBAL VARIABLES' : 'SELECT VARIABLE_NAME, VARIABLE_VALUE FROM performance_schema.global_variables') as [$name, $value]) {
            $globals[strtolower($name)] = $value;
            if ($value === '') {
                $empty[] = strtolower($name);
            }
        }
        if ($empty !== []) {
            $read = $native->query('SELECT ' . implode(', ', array_map(static fn (string $name): string => '@@GLOBAL.`' . str_replace('`', '``', $name) . '`', $empty)));
            $values = $read === false ? false : $read->fetch(PDO::FETCH_NUM);
            foreach ($empty as $position => $name) {
                if (is_array($values) && array_key_exists($position, $values) && $values[$position] === null) {
                    $globals[$name] = null;
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
    public function guard(PDO $connection, string $version, string $password): void
    {
        $account = "'" . self::GUARD . "'@'%'";
        $quoted = $connection->quote($password);
        $statements = str_starts_with($version, '5.6.')
            ? ["GRANT ALL ON *.* TO {$account} IDENTIFIED BY {$quoted} WITH GRANT OPTION"]
            : ["CREATE USER IF NOT EXISTS {$account} IDENTIFIED BY {$quoted}", "GRANT ALL ON *.* TO {$account} WITH GRANT OPTION"];
        $statements[] = "GRANT PROXY ON CURRENT_USER TO {$account} WITH GRANT OPTION";
        foreach ($statements as $statement) {
            $connection->exec($statement);
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
    public function clean(PDO $native, string $version = MySqlRelease::DEFAULT): void
    {
        $statements = [];
        foreach ($this->rows($native, 'SELECT User, Host FROM mysql.user') as [$name, $host]) {
            if (!in_array($name, ['root', self::GUARD, 'healthchecker', 'mysql.infoschema', 'mysql.session', 'mysql.sys'], true)) {
                $statements[] = (str_starts_with($version, '5.6.') ? 'DROP USER ' : 'DROP USER IF EXISTS ') . $native->quote($name) . '@' . $native->quote($host);
            }
        }
        foreach ($this->rows($native, 'SELECT Server_name FROM mysql.servers') as [$name]) {
            $statements[] = 'DROP SERVER IF EXISTS `' . str_replace('`', '``', $name) . '`';
        }
        foreach ($this->rows($native, 'SELECT RESOURCE_GROUP_NAME FROM information_schema.RESOURCE_GROUPS') as [$name]) {
            if (!in_array($name, ['SYS_default', 'USR_default'], true)) {
                $statements[] = 'DROP RESOURCE GROUP `' . str_replace('`', '``', $name) . '` FORCE';
            }
        }
        foreach (['db' => 'Db', 'tables_priv' => 'Db', 'columns_priv' => 'Db', 'procs_priv' => 'Db'] as $table => $column) {
            if ($this->rows($native, "SELECT 1 FROM mysql.{$table} WHERE User = 'root'") !== []) {
                $statements[] = "DELETE FROM mysql.{$table} WHERE User = 'root'";
            }
        }
        $statements[] = "DELETE FROM mysql.proxies_priv WHERE User = 'root' AND Host = '%'";
        $statements[] = 'FLUSH PRIVILEGES';
        foreach ($statements as $statement) {
            $native->exec($statement);
        }
    }

    /**
     * Answers the rows of a query as lists of strings, or none when the release lacks the table it reads.
     *
     * @return list<list<string>>
     */
    public function rows(PDO $native, string $sql): array
    {
        try {
            $statement = $native->query($sql);
        } catch (PDOException) {
            return [];
        }
        $rows = [];
        foreach ($statement === false ? [] : $statement->fetchAll(PDO::FETCH_NUM) as $row) {
            $values = [];
            foreach (is_array($row) ? $row : [] as $value) {
                $values[] = is_scalar($value) ? (string) $value : '';
            }
            $rows[] = $values;
        }

        return $rows;
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
     */
    public function routineTimestamps(PDO $native): array
    {
        $timestamps = [];
        foreach ($this->rows($native, "SELECT CONCAT(ROUTINE_TYPE, ':', ROUTINE_NAME), UNIX_TIMESTAMP(CREATED), UNIX_TIMESTAMP(LAST_ALTERED) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='sys'") as [$key, $created, $modified]) {
            $timestamps[$key] = [(int) $created, (int) $modified];
        }

        return $timestamps;
    }
    /**
     * Samples the platform capability with an isolated group before the differential fixture.
     *
     * The probe is independent of generated SQL and leaves no resource group behind.
     * @throws RuntimeException When the server returns an unexpected priority value
     */
    public function resourcePriorities(PDO $native, string $version): bool
    {
        if (str_starts_with($version, '5.')) {
            return true;
        }
        $name = 'memory_priority_' . bin2hex(random_bytes(8));
        $native->exec('CREATE RESOURCE GROUP ' . $name . ' TYPE USER THREAD_PRIORITY 1');
        try {
            $result = $native->query("SELECT THREAD_PRIORITY FROM information_schema.RESOURCE_GROUPS WHERE RESOURCE_GROUP_NAME = '" . $name . "'");
            $priority = $result === false ? false : $result->fetchColumn();
            if (!in_array($priority, [0, 1, '0', '1'], true)) {
                throw new RuntimeException('The resource-group priority probe returned no supported capability value.');
            }

            return $priority === 1 || $priority === '1';
        } finally {
            $native->exec('DROP RESOURCE GROUP ' . $name);
        }
    }

    /**
     * Reads the default group's processor domain before generated statements run.
     *
     * The fixture shares this platform capability; CPU validation errors are still compared exactly.
     * @return positive-int
     * @throws RuntimeException When the native default group does not describe a contiguous CPU domain
     */
    public function resourceProcessors(PDO $native, string $version): int
    {
        if (str_starts_with($version, '5.')) {
            return 8;
        }
        $result = $native->query("SELECT VCPU_IDS FROM information_schema.RESOURCE_GROUPS WHERE RESOURCE_GROUP_NAME = 'USR_default'");
        $cpus = $result === false ? false : $result->fetchColumn();
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
