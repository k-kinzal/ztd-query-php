<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use Closure;
use Fiber;
use FiberError;
use MySqlMemory\Instance;
use RuntimeException;

/**
 * Accepts client connections on a TCP or Unix socket and serves them one packet at a time.
 *
 * All connections share one instance and run in one process. A statement runs until it ends or
 * waits for a row lock another connection holds; the other connections are served while it
 * waits.
 *
 * @visibility MySqlMemory
 */
final class Listener
{
    /**
     * @var resource|null
     */
    private $server = null;

    private int $connections = 0;

    /**
     * @var array<int, Client> The connections, by socket id
     */
    private array $clients = [];

    /**
     * @var array<int, resource> The sockets of the connections, by socket id
     */
    private array $sockets = [];

    /**
     * @var array<int, object> The fibers of the connections whose statement waits for a lock, by socket id
     */
    private array $fibers = [];

    /**
     * @param Instance $instance The server the connections use
     * @param string $address Where to listen: `tcp://HOST:PORT` (port 0 picks a free one) or `unix:///PATH`
     */
    public function __construct(public readonly Instance $instance, public readonly string $address)
    {
    }

    /**
     * Opens the socket and answers the address it listens on.
     *
     * A socket that cannot be opened, for an unknown transport or an address in use, raises no PHP
     * warning: the reason PHP gives is the message of the exception.
     *
     * @throws RuntimeException When the socket cannot be opened
     */
    public function open(): string
    {
        set_error_handler(static fn (): bool => true);
        try {
            $server = stream_socket_server($this->address, $code, $message);
        } finally {
            restore_error_handler();
        }
        if ($server === false) {
            throw new RuntimeException('Cannot listen on ' . $this->address . ': ' . $message);
        }
        $this->server = $server;
        if (str_starts_with($this->address, 'unix://')) {
            return $this->address;
        }

        return 'tcp://' . stream_socket_get_name($server, false);
    }

    /**
     * Serves connections until the process is stopped.
     *
     * The packets of each connection are answered in a fiber of its own, so a statement that waits
     * for a row lock suspends it, and the other connections go on being served meanwhile; every
     * waiting connection checks again for its lock after each round of packets, and at least every
     * 10 milliseconds. A waiting connection reads no packets.
     *
     * @throws RuntimeException When the socket is not open
     */
    public function serve(): void
    {
        $server = $this->server;
        if ($server === null) {
            throw new RuntimeException('The listener is not open.');
        }
        while (!$this->instance->stopped) {
            $read = [$server];
            foreach ($this->sockets as $id => $socket) {
                if (!isset($this->fibers[$id])) {
                    $read[] = $socket;
                }
            }
            $write = null;
            $except = null;
            $waiting = $this->fibers !== [];
            set_error_handler(static fn (): bool => true);
            try {
                $selected = stream_select($read, $write, $except, $waiting ? 0 : null, $waiting ? 10000 : 0);
            } finally {
                restore_error_handler();
            }
            if ($selected === false) {
                continue;
            }
            foreach ($read as $socket) {
                if ($socket === $server) {
                    $this->accept();
                    continue;
                }
                $this->read((int) $socket);
            }
            foreach (array_keys($this->fibers) as $id) {
                $this->run($id);
            }
            $this->closeReleased();
        }
        fclose($server);
        $this->server = null;
    }

    /**
     * Reads available bytes and dispatches them, closing a socket whose peer disconnected.
     *
     * @param int $id The id returned by accept(), whose socket is readable
     * @throws FiberError When its work cannot start
     */
    public function read(int $id): void
    {
        $socket = $this->sockets[$id] ?? null;
        if ($socket === null) {
            return;
        }
        set_error_handler(static fn (): bool => true);
        try {
            $bytes = fread($socket, 1048576);
        } finally {
            restore_error_handler();
        }
        if ($bytes === false || $bytes === '') {
            $this->close($id);

            return;
        }
        $client = $this->clients[$id];
        $this->run($id, static fn (): bool => $client->receive($bytes));
    }

    /**
     * Closes idle connections ended by KILL, after waiting statements have answered their interruption.
     */
    public function closeReleased(): void
    {
        foreach ($this->clients as $id => $client) {
            if ($client->ended() && !isset($this->fibers[$id])) {
                $this->close($id);
            }
        }
    }

    /**
     * Accepts a connection, greets it, and answers its id; null when there is none to accept.
     */
    public function accept(): ?int
    {
        if ($this->server === null) {
            return null;
        }
        set_error_handler(static fn (): bool => true);
        try {
            $accepted = stream_socket_accept($this->server, 0);
        } finally {
            restore_error_handler();
        }
        if ($accepted === false) {
            return null;
        }
        stream_set_blocking($accepted, true);
        $id = (int) $accepted;
        $this->sockets[$id] = $accepted;
        $peer = (string) stream_socket_get_name($accepted, true);
        $host = str_contains($peer, ':') ? substr($peer, 0, (int) strrpos($peer, ':')) : 'localhost';
        $host = $host === '127.0.0.1' || $host === '::1' || $host === '' ? 'localhost' : $host;
        $this->clients[$id] = new Client($this->instance, ++$this->connections, static function (string $bytes) use ($accepted): void {
            for ($written = 0; $written < strlen($bytes);) {
                set_error_handler(static fn (): bool => true);
                try {
                    $sent = fwrite($accepted, substr($bytes, $written));
                } finally {
                    restore_error_handler();
                }
                if ($sent === false || $sent === 0) {
                    return;
                }
                $written += $sent;
            }
        }, $host);
        $this->clients[$id]->greet();

        return $id;
    }

    /**
     * Starts the work of a connection in a fiber, or resumes the fiber of the connection that waits; once it is done, a connection whose client quit is closed.
     *
     * @param Closure(): bool|null $work The work started, which answers false when the client quits, or null to resume the waiting fiber
     *
     * @throws FiberError When the fiber cannot be started or resumed
     */
    public function run(int $id, ?Closure $work = null): void
    {
        $fiber = $work === null ? $this->fibers[$id] ?? null : new Fiber($work);
        if (!$fiber instanceof Fiber) {
            return;
        }
        $scheduler = $this->instance->transactions->scheduler;
        $scheduler->admit($fiber);
        if ($work === null) {
            $fiber->resume();
        } else {
            $fiber->start();
        }
        if (!$fiber->isTerminated()) {
            $this->fibers[$id] = $fiber;

            return;
        }
        $scheduler->dismiss($fiber);
        unset($this->fibers[$id]);
        if ($fiber->getReturn() === false) {
            $this->close($id);
        }
    }

    /**
     * Closes a connection and ends its session.
     */
    public function close(int $id): void
    {
        if (isset($this->sockets[$id])) {
            fclose($this->sockets[$id]);
        }
        ($this->clients[$id] ?? null)?->close();
        unset($this->sockets[$id], $this->clients[$id], $this->fibers[$id]);
    }
}
