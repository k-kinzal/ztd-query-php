<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use MySqlMemory\Instance;
use RuntimeException;

/**
 * Accepts client connections on a TCP or Unix socket and serves them one packet at a time.
 *
 * All connections share one instance; statements of different connections never interleave.
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
     * @param Instance $instance The server the connections use
     * @param string $address Where to listen: `tcp://HOST:PORT` (port 0 picks a free one) or `unix:///PATH`
     */
    public function __construct(public readonly Instance $instance, public readonly string $address)
    {
    }

    /**
     * Opens the socket and answers the address it listens on.
     *
     * @throws RuntimeException When the socket cannot be opened
     */
    public function open(): string
    {
        $server = @stream_socket_server($this->address, $code, $message);
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
     * @throws RuntimeException When the socket is not open
     */
    public function serve(): void
    {
        if ($this->server === null) {
            throw new RuntimeException('The listener is not open.');
        }
        $clients = [];
        $sockets = [];
        for (;;) {
            $read = [$this->server, ...$sockets];
            $write = null;
            $except = null;
            if (@stream_select($read, $write, $except, null) === false) {
                continue;
            }
            foreach ($read as $socket) {
                if ($socket === $this->server) {
                    $accepted = @stream_socket_accept($this->server, 0);
                    if ($accepted === false) {
                        continue;
                    }
                    stream_set_blocking($accepted, true);
                    $id = (int) $accepted;
                    $sockets[$id] = $accepted;
                    $peer = (string) stream_socket_get_name($accepted, true);
                    $host = str_contains($peer, ':') ? substr($peer, 0, (int) strrpos($peer, ':')) : 'localhost';
                    $host = $host === '127.0.0.1' || $host === '::1' || $host === '' ? 'localhost' : $host;
                    $clients[$id] = new Client($this->instance, ++$this->connections, static function (string $bytes) use ($accepted): void {
                        for ($written = 0; $written < strlen($bytes);) {
                            $sent = @fwrite($accepted, substr($bytes, $written));
                            if ($sent === false || $sent === 0) {
                                return;
                            }
                            $written += $sent;
                        }
                    }, $host);
                    $clients[$id]->greet();
                    continue;
                }
                $id = (int) $socket;
                $bytes = @fread($socket, 1048576);
                if ($bytes === false || $bytes === '' || !$clients[$id]->receive($bytes)) {
                    fclose($socket);
                    unset($sockets[$id], $clients[$id]);
                }
            }
        }
    }
}
