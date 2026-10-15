<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Container\Endpoint;
use JsonException;
use MySqlMemory\Server\Server;
use PDO;
use PDOException;
use Testcontainers\Testcontainers;

/**
 * Compares SHUTDOWN and RESTART on disposable servers, retaining these inputs in the seed corpus.
 *
 * Their completion, connection termination and ability to reconnect are observed. A successful
 * restart must preserve the fixture rows and reset aborted-client statistics. Post-statement SHOW WARNINGS cannot be observed
 * reliably on a connection the server is closing; errors still record their full diagnostics.
 */
final class Lifecycle
{
    /**
     * Tells whether an input needs a disposable server instead of the shared differential fixture.
     */
    public static function handles(string $sql): bool
    {
        return preg_match('/\A\s*(?:SHUTDOWN|RESTART)\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Compares a lifecycle input, even when a regular campaign uses an externally supplied DSN.
     *
     * @throws JsonException When an observation cannot be serialized
     */
    public function compare(string $sql, string $version): Comparison
    {
        $definition = new DisposableMySql($version);
        $port = filter_var(getenv('MYSQL_MEMORY_LIFECYCLE_NATIVE_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (is_int($port)) {
            $definition->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port));
        }
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $dsn = 'mysql:host=' . $endpoint->host . ';port=' . $endpoint->port;
            $native = new PDO($dsn, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $globals = [];
            foreach ((new Servers())->rows($native, 'SHOW GLOBAL VARIABLES') as [$name, $value]) {
                $globals[strtolower($name)] = $value;
            }
            $server = Server::start($version, [], $globals, supervised: $definition->supervised(), resourcePriorities: (new Servers())->resourcePriorities($native, $version), resourceProcessors: (new Servers())->resourceProcessors($native, $version));
            try {
                $expected = $this->observe($native, $dsn, $endpoint->username, $endpoint->password, $sql);
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $actual = $this->observe($memory, $server->dsn(), 'root', '', $sql);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }

        return new Comparison(false, $expected === $actual ? null : "lifecycle\n  expected: " . json_encode($expected, JSON_THROW_ON_ERROR) . "\n  actual:   " . json_encode($actual, JSON_THROW_ON_ERROR));
    }

    /**
     * Executes one lifecycle statement from the same fixture, then waits for its observable transition.
     *
     * @return array<string, mixed>
     */
    public function observe(PDO $pdo, string $dsn, string $user, string $password, string $sql): array
    {
        $pdo->exec('CREATE DATABASE fz');
        $pdo->exec('USE fz');
        foreach ((new Fixture())->statements() as $statement) {
            (new Fixture())->execute($pdo, $statement);
        }
        $observer = new Observer();
        try {
            $statement = $pdo->query($sql);
            $result = $statement === false ? null : $observer->result($pdo, $statement, true);
            if ($statement !== false) {
                $statement->closeCursor();
            }
        } catch (PDOException $error) {
            return ['error' => $error->errorInfo, 'warnings' => $observer->warnings($pdo)];
        }
        $restart = str_starts_with(strtoupper(ltrim($sql)), 'RESTART');
        [$disconnected, $again] = $this->transition($pdo, $dsn, $user, $password, $restart);

        return ['result' => $result, 'disconnected' => $disconnected, 'reconnected' => $again !== null, 'tables' => $again === null ? null : $observer->tables($again, 'fz'), 'status' => $again === null ? null : $observer->observe($again, "SHOW GLOBAL STATUS LIKE 'Aborted_clients'", true)];
    }

    /**
     * Waits for the old connection to close and for shutdown to stop serving SQL.
     * Both shutdown checks share the existing ten-second transition budget. Restart
     * retains its separate thirty-second reconnection budget after disconnection.
     *
     * @return array{bool, PDO|null}
     */
    public function transition(PDO $pdo, string $dsn, string $user, string $password, bool $restart): array
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $deadline = microtime(true) + 10;
        $disconnected = false;
        $again = null;
        do {
            $disconnected = $disconnected || $pdo->exec('DO 0') === false;
            if ($disconnected) {
                $again = $this->reconnect($dsn, $user, $password, $restart);
                if ($restart || $again === null) {
                    return [true, $again];
                }
            }
            usleep(20000);
        } while (microtime(true) < $deadline);

        return [$disconnected, $again];
    }

    /**
     * Attempts one usable SQL connection, or retries for up to thirty seconds after restart.
     * Authentication alone can succeed while a server is shutting down.
     */
    public function reconnect(string $dsn, string $user, string $password, bool $wait): ?PDO
    {
        $deadline = microtime(true) + ($wait ? 30 : 0);
        do {
            try {
                $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 1]);
            } catch (PDOException) {
                $pdo = null;
            }
            if ($pdo !== null && $this->ready($pdo)) {
                return $pdo;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);

        return null;
    }

    /**
     * Confirms an authenticated connection can still execute SQL.
     */
    public function ready(PDO $pdo): bool
    {
        try {
            return $pdo->exec('DO 0') !== false;
        } catch (PDOException) {
            return false;
        }
    }
}
