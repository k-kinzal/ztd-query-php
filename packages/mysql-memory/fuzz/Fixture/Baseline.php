<?php

declare(strict_types=1);

namespace Fuzz\Fixture;

use Fuzz\Target\ExecutionTarget;
use PDO;
use RuntimeException;

/**
 * Restores the disposable servers between observations, including state outside the fixture database.
 *
 * Each server's own catalog determines its cleanup. A reference-only inventory
 * would miss objects created erroneously by mysql-memory. Catalog reads and
 * restoration are checked; no generated statement is excluded.
 */
final class Baseline
{
    /**
     * @var array<string, true> The inverse statements discovered on the connection being restored
     */
    public array $cleanup = [];

    /**
     * @var array<string, string|null> The initial global system variables
     */
    public array $globals = [];

    /**
     * The original account's CREATE statement, preserving authentication and options.
     */
    public readonly ?string $account;

    /**
     * The quoted account under test, distinct from the administrator restoring it.
     */
    public readonly string $identity;

    /**
     * @var array{string, string} The account name and host used by catalog predicates
     */
    public readonly array $principal;

    /**
     * The original credential-change instant, absent on 5.6 or for an external authentication plugin.
     */
    public readonly ?int $passwordChanged;

    /**
     * @var list<string> The original grants of the account under test
     */
    public readonly array $grants;

    /**
     * The spatial catalog shared by each isolated observation.
     */
    public readonly SpatialBaseline $spatial;

    /**
     * @var list<string> General tablespaces installed before generated input, including the MySQL dictionary
     */
    public readonly array $tablespaces;

    /**
     * Captures the original global variables and authentication string without generating a new password salt.
     *
     * @throws RuntimeException When the initial spatial catalog cannot be captured
     */
    public function __construct(PDO $native, public readonly string $version)
    {
        $this->spatial = new SpatialBaseline($native, $version);
        $this->tablespaces = $this->generalTablespaces($native);
        $server = new Servers();
        [$user, $host] = explode('@', $server->rows($native, 'SELECT CURRENT_USER()')[0][0], 2);
        $this->principal = [$user, $host];
        $this->identity = $native->quote($user) . '@' . $native->quote($host);
        $this->grants = array_column($server->rows($native, 'SHOW GRANTS FOR CURRENT_USER'), 0);
        $this->globals = $server->globals($native, $version);
        $account = str_starts_with($version, '5.6.') ? [] : (new Servers())->rows($native, 'SHOW CREATE USER CURRENT_USER');
        $this->account = $account === [] ? null : $account[0][1] ?? $account[0][0];
        $this->passwordChanged = $this->passwordChanged($native);
    }

    /**
     * Restores both observations to an empty application catalog and the captured server configuration.
     * The caller first repairs the administrator and read-only modes.
     *
     * @throws RuntimeException When the original account, grants or global variables cannot be restored
     */
    public function restore(PDO $connection): void
    {
        $this->discover($connection);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        (new Tables())->remove($connection, ExecutionTarget::DATABASE, $this->version);
        foreach ($this->cleanup as $statement => $_) {
            (new Tables())->execute($connection, $statement);
        }
        $this->spatial->restore($connection);
        /**
         * Account rows can have a lossy host encoding while the cache retains the original spelling.
         * Reload after deletion so both observations start without the stale cache entry.
         */
        (new Tables())->execute($connection, 'FLUSH PRIVILEGES');
        if ($this->account !== null) {
            /** ALTER USER without a credential expires the password; replay the original CREATE instead. */
            (new Tables())->execute($connection, 'SET timestamp = ' . ($this->passwordChanged ?? 0));
            try {
                (new Tables())->execute($connection, 'DROP USER ' . $this->identity);
                (new Tables())->execute($connection, $this->account);
            } finally {
                (new Tables())->execute($connection, 'SET timestamp = 0');
            }
        } else {
            (new Tables())->execute($connection, 'REVOKE ALL PRIVILEGES, GRANT OPTION FROM ' . $this->identity);
        }
        foreach ($this->grants as $grant) {
            if ($connection->exec($grant) === false) {
                throw new RuntimeException('Cannot restore reference grants: ' . json_encode($connection->errorInfo()));
            }
        }
        foreach ((new Servers())->globals($connection, $this->version) as $name => $value) {
            if (array_key_exists($name, $this->globals) && $this->globals[$name] !== $value) {
                $original = $this->globals[$name];
                $literal = $original === null
                    ? (str_starts_with($name, 'innodb_monitor_') ? 'DEFAULT' : 'NULL')
                    : (preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', $original) === 1 ? $original : $connection->quote($original));
                if ($connection->exec('SET GLOBAL ' . $this->identifier($name) . ' = ' . $literal) === false) {
                    throw new RuntimeException('Cannot restore the global variable ' . $name . ': ' . json_encode($connection->errorInfo()));
                }
            }
        }
        $this->assertRestored($connection);
    }

    /**
     * Verifies the observed catalog, grants, account and globals before another input can run.
     *
     * @throws RuntimeException When any part of the initial fixture remains different
     */
    public function assertRestored(PDO $connection): void
    {
        $this->discover($connection);
        if ($this->cleanup !== [] || $this->spatial->changed !== []) {
            throw new RuntimeException('The differential catalog was not restored.');
        }
        $server = new Servers();
        $globals = $server->globals($connection, $this->version);
        $expected = $this->globals;
        ksort($globals);
        ksort($expected);
        if ($globals !== $expected) {
            throw new RuntimeException('The differential global variables were not restored.');
        }
        $grants = array_column($server->rows($connection, 'SHOW GRANTS FOR ' . $this->identity), 0);
        $expectedGrants = $this->grants;
        sort($grants);
        sort($expectedGrants);
        if ($grants !== $expectedGrants) {
            throw new RuntimeException('The differential grants were not restored.');
        }
        if ($this->account !== null) {
            $account = $server->rows($connection, 'SHOW CREATE USER ' . $this->identity);
            $definition = $account[0][1] ?? $account[0][0] ?? null;
            if ($definition !== $this->account) {
                throw new RuntimeException('The differential account was not restored.');
            }
            if ($this->passwordChanged($connection) !== $this->passwordChanged) {
                throw new RuntimeException('The differential credential-change instant was not restored.');
            }
        }
    }

    /**
     * Reads the credential age separately from SHOW CREATE USER, which does not include it.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
     *
     * @throws RuntimeException When credential age is unreadable or outside the timestamp domain
     */
    public function passwordChanged(PDO $connection): ?int
    {
        if (str_starts_with($this->version, '5.6.')) {
            return null;
        }
        $statement = $connection->query('SELECT UNIX_TIMESTAMP(password_last_changed) FROM mysql.user WHERE User = ' . $connection->quote($this->principal[0]) . ' AND Host = ' . $connection->quote($this->principal[1]));
        $instant = $statement === false ? false : $statement->fetchColumn();
        if ($instant === null) {
            return null;
        }
        $seconds = filter_var($instant, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($instant === false || $seconds === false) {
            throw new RuntimeException('Cannot read the differential credential-change instant.');
        }
        return $seconds;
    }

    /**
     * Starts each isolated observation after fixture writes with one empty binary log.
     *
     * The same SQL resets both disposable servers. Generated statements remain logged, and
     * their results are compared without replacing positions, event information or rows.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/reset-binary-logs-and-gtids.html.
     */
    public function logs(PDO $connection): void
    {
        if (($this->globals['log_bin'] ?? 'OFF') !== 'ON') {
            return;
        }
        (new Tables())->execute($connection, version_compare($this->version, '8.4.0', '>=') ? 'RESET BINARY LOGS AND GTIDS' : 'RESET MASTER');
    }

    /**
     * Clears historical latch waits before an isolated observation, using the same SQL on both servers.
     * The original selector is restored with the other globals after the observation.
     * Counters produced during the generated input remain visible and are never normalized.
     * MySQL 5.6 does not recognize latch; this fixture is enabled only for verified modern releases.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engine.html.
     */
    public static function mutexes(PDO $connection, string $version): void
    {
        if (version_compare($version, '8.0.0', '>=')) {
            (new Tables())->execute($connection, "SET GLOBAL innodb_monitor_reset='latch'");
        }
    }

    /**
     * Records inverse statements for all generated catalog objects, including replication channels.
     *
     * @throws RuntimeException When an original fixture tablespace is missing
     */
    public function discover(PDO $native): void
    {
        $this->cleanup = [];
        $this->spatial->discover($native);
        $server = new Servers();
        foreach ($server->rows($native, 'SHOW DATABASES') as [$name]) {
            if (!in_array($name, ['mysql', 'sys', 'information_schema', 'performance_schema', ExecutionTarget::DATABASE], true)) {
                $this->cleanup['DROP DATABASE IF EXISTS ' . $this->identifier($name)] = true;
            }
        }
        foreach ([...$server->rows($native, 'SELECT User, Host FROM mysql.user'), ...(str_starts_with($this->version, '5.') ? [] : $server->rows($native, 'SELECT User, Host FROM mysql.password_history'))] as [$user, $host]) {
            if (!in_array($user, ['root', Servers::GUARD, 'healthchecker', 'mysql.infoschema', 'mysql.session', 'mysql.sys'], true)) {
                $this->cleanup['DROP USER ' . (str_starts_with($this->version, '5.6.') ? '' : 'IF EXISTS ') . $native->quote($user) . '@' . $native->quote($host)] = true;
            }
        }
        foreach ($server->rows($native, 'SELECT Server_name FROM mysql.servers') as [$name]) {
            $this->cleanup['DROP SERVER IF EXISTS ' . $this->identifier($name)] = true;
        }
        foreach ($server->rows($native, 'SELECT User, Host, Proxied_user, Proxied_host FROM mysql.proxies_priv') as [$user, $host, $proxiedUser, $proxiedHost]) {
            $identity = $native->quote($user) . '@' . $native->quote($host);
            if ($identity === $this->identity) {
                $this->cleanup['REVOKE PROXY ON ' . $native->quote($proxiedUser) . '@' . $native->quote($proxiedHost) . ' FROM ' . $identity] = true;
            }
        }
        foreach (str_starts_with($this->version, '5.') ? [] : $server->rows($native, 'SELECT RESOURCE_GROUP_NAME FROM information_schema.RESOURCE_GROUPS') as [$name]) {
            if (!in_array($name, ['SYS_default', 'USR_default'], true)) {
                $this->cleanup['DROP RESOURCE GROUP ' . $this->identifier($name) . ' FORCE'] = true;
            }
        }
        $tablespaces = $this->generalTablespaces($native);
        if (array_diff($this->tablespaces, $tablespaces) !== []) {
            throw new RuntimeException('Original fixture tablespaces are missing: ' . implode(', ', array_diff($this->tablespaces, $tablespaces)));
        }
        foreach (array_diff($tablespaces, $this->tablespaces) as $name) {
            $this->cleanup['DROP TABLESPACE ' . $this->identifier($name)] = true;
        }
        $command = str_starts_with($this->version, '5.') ? 'SLAVE' : 'REPLICA';
        if ($server->rows($native, 'SHOW ' . $command . ' STATUS') !== []) {
            $this->cleanup['STOP ' . $command] = true;
            $this->cleanup['RESET ' . $command . ' ALL'] = true;
        }
    }

    /**
     * Reads the general tablespace inventory exposed by the selected release.
     *
     * MySQL 5.6 has no general tablespaces. MySQL 5.7 exposes INNODB_SYS_TABLESPACES;
     * later releases expose INNODB_TABLESPACES, including the reserved mysql space.
     * Source: https://dev.mysql.com/doc/mysql-infoschema-excerpt/5.7/en/information-schema-innodb-sys-tablespaces-table.html.
     *
     * @return list<string>
     */
    public function generalTablespaces(PDO $connection): array
    {
        if (str_starts_with($this->version, '5.6.')) {
            return [];
        }
        $table = str_starts_with($this->version, '5.7.') ? 'INNODB_SYS_TABLESPACES' : 'INNODB_TABLESPACES';
        return array_column((new Servers())->rows($connection, "SELECT NAME FROM information_schema.{$table} WHERE SPACE_TYPE = 'General'"), 0);
    }

    /**
     * Quotes a catalog identifier for a cleanup statement.
     */
    public function identifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
