<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Container\Endpoint;
use Container\MySqlRelease;
use MySqlMemory\Server\Server;
use PDO;
use PDOException;
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
     * Starts both servers and answers the differential target over them, and the grammar of the release.
     *
     * @return array{Differential, string, Server}
     */
    public function start(bool $emulate = true): array
    {
        $version = getenv('MYSQL_VERSION') !== false ? (string) getenv('MYSQL_VERSION') : MySqlRelease::DEFAULT;
        $dsn = getenv('MYSQL_MEMORY_NATIVE_DSN');
        if ($dsn !== false && $dsn !== '') {
            $user = $this->environment('MYSQL_MEMORY_NATIVE_USER', 'root');
            $password = $this->environment('MYSQL_MEMORY_NATIVE_PASSWORD', 'root');
        } else {
            $endpoint = Testcontainers::run(MySqlRelease::container($version))->getData(Endpoint::class);
            $dsn = 'mysql:host=' . $endpoint->host . ';port=' . $endpoint->port;
            $user = $endpoint->username;
            $password = $endpoint->password;
        }
        $native = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->clean($native, $version);
        $globals = [];
        foreach ($this->rows($native, str_starts_with($version, '5.6.') ? 'SHOW GLOBAL VARIABLES' : 'SELECT VARIABLE_NAME, VARIABLE_VALUE FROM performance_schema.global_variables') as [$name, $value]) {
            $globals[strtolower($name)] = $value;
        }
        $identity = $native->query('SELECT USER()');
        $account = $identity === false ? '' : $identity->fetchColumn();
        $account = is_string($account) ? $account : '';
        $server = Server::start($version, [], $globals, substr($account, (int) strrpos($account, '@') + 1));

        return [new Differential($dsn, $user, $password, $server->dsn(), $emulate, $version), 'mysql-' . $version, $server];
    }

    /**
     * Removes what statements of earlier runs left on the MySQL server outside the fixture database.
     *
     * Accounts and roles other than root and the server's own, foreign servers, resource groups
     * other than the defaults, and the database, table and column grants of root are dropped,
     * so that the MySQL server starts a run in the state mysql-memory starts in. MySQL 5.6 lacks
     * DROP USER IF EXISTS; the accounts it drops are those it lists, so it drops them without.
     */
    public function clean(PDO $native, string $version = MySqlRelease::DEFAULT): void
    {
        $statements = [];
        foreach ($this->rows($native, 'SELECT User, Host FROM mysql.user') as [$name, $host]) {
            if (!in_array($name, ['root', 'healthchecker', 'mysql.infoschema', 'mysql.session', 'mysql.sys'], true)) {
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
}
