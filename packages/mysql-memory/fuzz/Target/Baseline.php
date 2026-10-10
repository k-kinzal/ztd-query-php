<?php

declare(strict_types=1);

namespace Fuzz\Target;

use PDO;
use RuntimeException;

/**
 * Restores the disposable servers between observations, including state outside the fixture database.
 *
 * Cleanup discovered on MySQL is retained and also applied to mysql-memory, whose system catalogs
 * need not implement the introspection queries used by the harness. No generated statement is excluded.
 */
final class Baseline
{
    /**
     * @var array<string, true> The inverse statements discovered on the reference server
     */
    public array $cleanup = [];

    /**
     * @var array<string, string> The initial global system variables
     */
    public array $globals = [];

    /**
     * The statement restoring the original account authentication and options.
     */
    public readonly ?string $account;

    /**
     * The quoted account under test, distinct from the administrator restoring it.
     */
    public readonly string $identity;

    /**
     * @var list<string> The original grants of the account under test
     */
    public readonly array $grants;

    /**
     * Captures the original global variables and authentication string without generating a new password salt.
     */
    public function __construct(PDO $native, public readonly string $version)
    {
        $server = new Servers();
        [$user, $host] = explode('@', $server->rows($native, 'SELECT CURRENT_USER()')[0][0], 2);
        $this->identity = $native->quote($user) . '@' . $native->quote($host);
        $this->grants = array_column($server->rows($native, 'SHOW GRANTS FOR CURRENT_USER'), 0);
        foreach ((new Servers())->rows($native, 'SHOW GLOBAL VARIABLES') as [$name, $value]) {
            $this->globals[strtolower($name)] = $value;
        }
        $account = (new Servers())->rows($native, 'SHOW CREATE USER CURRENT_USER');
        $this->account = $account === [] ? null : (string) preg_replace('/\ACREATE USER /', 'ALTER USER ', $account[0][1] ?? $account[0][0]);
    }

    /**
     * Restores both observations to an empty application catalog and the captured server configuration.
     * The caller first repairs the administrator and read-only modes.
     *
     * @throws RuntimeException When the original account, grants or global variables cannot be restored
     */
    public function restore(PDO $connection, bool $native): void
    {
        if ($native) {
            $this->discover($connection);
        }
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        foreach ($this->cleanup as $statement => $_) {
            $connection->exec($statement);
        }
        $connection->exec('REVOKE ALL PRIVILEGES, GRANT OPTION FROM ' . $this->identity);
        foreach ($this->grants as $grant) {
            if ($connection->exec($grant) === false) {
                throw new RuntimeException('Cannot restore reference grants: ' . json_encode($connection->errorInfo()));
            }
        }
        if ($this->account !== null && $connection->exec($this->account) === false) {
            throw new RuntimeException('Cannot restore the reference account: ' . json_encode($connection->errorInfo()));
        }
        foreach ((new Servers())->rows($connection, 'SHOW GLOBAL VARIABLES') as [$name, $value]) {
            $key = strtolower($name);
            if (isset($this->globals[$key]) && $this->globals[$key] !== $value) {
                $original = $this->globals[$key];
                $literal = preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', $original) === 1 ? $original : $connection->quote($original);
                if ($connection->exec('SET GLOBAL ' . $this->identifier($name) . ' = ' . $literal) === false) {
                    throw new RuntimeException('Cannot restore the global variable ' . $name . ': ' . json_encode($connection->errorInfo()));
                }
            }
        }
    }

    /**
     * Records inverse statements for all generated catalog objects, including replication channels.
     */
    public function discover(PDO $native): void
    {
        $server = new Servers();
        foreach ($server->rows($native, 'SHOW DATABASES') as [$name]) {
            if (!in_array($name, ['mysql', 'sys', 'information_schema', 'performance_schema', Differential::DATABASE], true)) {
                $this->cleanup['DROP DATABASE IF EXISTS ' . $this->identifier($name)] = true;
            }
        }
        foreach ($server->rows($native, 'SELECT User, Host FROM mysql.user') as [$user, $host]) {
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
        foreach ($server->rows($native, 'SELECT RESOURCE_GROUP_NAME FROM information_schema.RESOURCE_GROUPS') as [$name]) {
            if (!in_array($name, ['SYS_default', 'USR_default'], true)) {
                $this->cleanup['DROP RESOURCE GROUP ' . $this->identifier($name) . ' FORCE'] = true;
            }
        }
        foreach ($server->rows($native, "SELECT NAME FROM information_schema.INNODB_TABLESPACES WHERE SPACE_TYPE = 'General'") as [$name]) {
            $this->cleanup['DROP TABLESPACE ' . $this->identifier($name)] = true;
        }
        foreach (['performance_schema.replication_connection_configuration', 'mysql.slave_master_info', 'mysql.slave_relay_log_info'] as $table) {
            if ($server->rows($native, 'SELECT CHANNEL_NAME FROM ' . $table) !== []) {
                $command = str_starts_with($this->version, '5.') ? 'SLAVE' : 'REPLICA';
                $this->cleanup['STOP ' . $command] = true;
                $this->cleanup['RESET ' . $command . ' ALL'] = true;
            }
        }
    }

    /**
     * Quotes a catalog identifier for a cleanup statement.
     */
    public function identifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
