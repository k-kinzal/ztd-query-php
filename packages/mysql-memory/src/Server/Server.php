<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use RuntimeException;

/**
 * A server process clients connect to with PDO or mysqli, as to a MySQL server.
 *
 * start() runs bin/mysql-memory in a child process that listens on a free local port and holds
 * the databases in its memory; dsn() names it for PDO. The process ends with stop(), or when
 * this object is destroyed.
 *
 * @visibility public
 * @example Connecting with PDO
 *     $server = \MySqlMemory\Server\Server::start();
 *     $pdo = new \PDO($server->dsn(), 'root', '');
 *     $pdo->query('SELECT 1 + 1')->fetchColumn() // => 2
 *     $server->stop();
 */
final class Server
{
    /**
     * @param resource $process The child process
     * @param string $host The host the server listens on
     * @param int $port The port the server listens on
     */
    public function __construct(private $process, public readonly string $host, public readonly int $port)
    {
    }

    /**
     * Starts a server process.
     *
     * @param string $version The MySQL release emulated
     * @param list<string> $databases Databases created at start
     * @param array<string, string|int> $globals Global variable values the server starts with
     * @param string|null $clientHost The host every client is seen connecting from, or null for its address
     * @param bool $supervised Whether SQL RESTART is available
     *
     * @throws RuntimeException When the process cannot be started
     */
    public static function start(string $version = '8.4.7', array $databases = [], array $globals = [], ?string $clientHost = null, bool $supervised = true): self
    {
        $command = [PHP_BINARY, '-d', 'memory_limit=-1', '-d', 'xdebug.mode=off', dirname(__DIR__, 2) . '/bin/mysql-memory', '--listen=tcp://127.0.0.1:0', '--release=' . $version];
        if (!$supervised) {
            $command[] = '--unsupervised';
        }
        foreach ($databases as $database) {
            $command[] = '--database=' . $database;
        }
        if ($clientHost !== null) {
            $command[] = '--client-host=' . $clientHost;
        }
        foreach ($globals as $name => $value) {
            $command[] = '--global=' . $name . '=' . $value;
        }
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('The server process could not be started.');
        }
        $line = fgets($pipes[1]);
        if ($line === false || preg_match('/\Aready tcp:\/\/([^:]+):([0-9]+)/', $line, $match) !== 1) {
            proc_terminate($process);
            throw new RuntimeException('The server process did not start: ' . (string) $line);
        }

        return new self($process, $match[1], (int) $match[2]);
    }

    /**
     * Answers the PDO data source name of the server, with a database when one is given.
     */
    public function dsn(?string $database = null): string
    {
        return 'mysql:host=' . $this->host . ';port=' . $this->port . ($database === null ? '' : ';dbname=' . $database);
    }

    /**
     * Stops the server process; its databases are gone.
     */
    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process, 9);
            proc_close($this->process);
        }
    }

    /**
     * Stops the server process when the object is destroyed.
     */
    public function __destruct()
    {
        $this->stop();
    }
}
