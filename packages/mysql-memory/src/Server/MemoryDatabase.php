<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use RuntimeException;
use SqlSemantics\Contract\GrammarRelease;

/**
 * A server process clients connect to with PDO or mysqli, as to a MySQL server.
 *
 * start() runs the library listener in a child process that listens on a free local port and holds
 * the databases in its memory; dsn() names it for PDO. The process ends with stop(), or when
 * this object is destroyed.
 *
 * @visibility public
 * @example Connecting with PDO
 *     $server = \MySqlMemory\Server\MemoryDatabase::start('8.4.7');
 *     $pdo = new \PDO($server->dsn(), 'root', '');
 *     $pdo->query('SELECT 1 + 1')->fetchColumn() // => 2
 *     $server->stop();
 */
final class MemoryDatabase
{
    private ?string $failure = null;

    private bool $exited = false;

    private readonly Failures $failures;

    /**
     * @param resource $process The child process
     * @param resource $messages The process readiness and failure channel, separate from SQL replies
     * @param string $host The host the server listens on
     * @param int $port The port the server listens on
     * @param string $version The selected MySQL release
     */
    private function __construct(private $process, private $messages, public readonly string $host, public readonly int $port, public readonly string $version)
    {
        $this->failures = new Failures($messages);
    }

    /**
     * Starts a server process.
     *
     * @param list<array{string, int, bool}>|null $timeZones Installed names, identifiers and leap-second flags; null uses PHP’s IANA database, [] disables named zones
     * @param string $version The MySQL release emulated
     * @param list<string> $databases Databases created at start
     * @param array<string, string|int|null> $globals Global variable values the server starts with
     * @param string|null $clientHost The host every client is seen connecting from, or null for its address
     * @param bool $supervised Whether SQL RESTART is available
     * @param bool $resourcePriorities Whether the emulated platform applies resource-group thread priorities
     * @param array<string, list<list<string>>> $help The initial help table data, in the documented column order
     * @param positive-int $resourceProcessors The number of logical processors visible to resource groups
     * @param array<string, array{int, int}> $routineTimestamps Installation creation and modification epochs, keyed by FUNCTION:name or PROCEDURE:name
     *
     * @throws RuntimeException When the release is not supported
     * @throws RuntimeException When the process cannot be started
     */
    public static function start(string $version, array $databases = [], array $globals = [], ?string $clientHost = null, bool $supervised = true, array $routineTimestamps = [], bool $resourcePriorities = true, int $resourceProcessors = 8, array $help = [], ?array $timeZones = null): self
    {
        if (GrammarRelease::tryFrom('mysql-' . $version) === null) {
            throw new RuntimeException('Unsupported MySQL release: ' . $version);
        }
        $options = ['version' => $version, 'databases' => $databases, 'globals' => $globals, 'clientHost' => $clientHost, 'supervised' => $supervised, 'routineTimestamps' => $routineTimestamps, 'resourcePriorities' => $resourcePriorities, 'resourceProcessors' => $resourceProcessors];
        $autoload = is_file(dirname(__DIR__, 2) . '/vendor/autoload.php') ? dirname(__DIR__, 2) . '/vendor/autoload.php' : dirname(__DIR__, 4) . '/autoload.php';
        $command = [PHP_BINARY, '-d', 'memory_limit=-1', '-d', 'xdebug.mode=off', '-r', 'require $argv[1]; (new \\MySqlMemory\\Server\\Process())->serve($argv[2]);', $autoload, serialize($options)];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('The server process could not be started.');
        }
        if ($timeZones !== null) {
            $help['zones'] = array_map(static fn (array $zone): array => [$zone[0], (string) $zone[1], $zone[2] ? 'Y' : 'N'], $timeZones);
        }
        (new InitialTables())->write($pipes[0], $help);
        fclose($pipes[0]);
        $line = fgets($pipes[1]);
        if ($line === false || preg_match('/\Aready tcp:\/\/([^:]+):([0-9]+)/', $line, $match) !== 1) {
            proc_terminate($process);
            proc_close($process);
            fclose($pipes[1]);
            throw new RuntimeException('The server process did not start: ' . (string) $line);
        }

        stream_set_blocking($pipes[1], false);
        return new self($process, $pipes[1], $match[1], (int) $match[2], $version);
    }

    /**
     * Fails when the server encountered an implementation error, independently of a client's SQL reply.
     * A deliberate SQL SHUTDOWN exits normally and is not an internal failure.
     *
     * @throws RuntimeException When the child reports a failure or exits unsuccessfully
     */
    public function assertNoInternalFailure(): void
    {
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }
        if (is_resource($this->messages)) {
            $this->failures->assertNone();
        }
        if (!$this->exited && is_resource($this->process)) {
            $status = proc_get_status($this->process);
            $this->exited = !$status['running'];
            if ($this->exited && $status['exitcode'] !== 0) {
                $this->failure = 'mysql-memory process failed with exit status ' . $status['exitcode'];
                throw new RuntimeException($this->failure);
            }
        }
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
        if (is_resource($this->messages)) {
            fclose($this->messages);
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
