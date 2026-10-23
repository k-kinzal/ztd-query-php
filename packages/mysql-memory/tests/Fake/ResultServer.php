<?php

declare(strict_types=1);

namespace Tests\Fake;

use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use MySqlMemory\Protocol\Messages;
use MySqlMemory\Protocol\PayloadWriter;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Server\Client;
use RuntimeException;

/**
 * Observes interleaved response bytes with the real driver, without executing SQL.
 * A nested result can be decoded as malformed caller rows; guessing that those
 * rows are empty loses observations on legacy binary connections. The child only
 * serves supplied packets. It has no parser, semantic input or execution path.
 */
final class ResultServer
{
    /**
     * @return array{columns: list<array<string, int|string>>, rows: list<list<string|null>>} The driver's result metadata and decoded rows
     * @throws RuntimeException When the transport fixture cannot replay the response
     */
    public function result(string $version, ResultSet $result, bool $binary): array
    {
        $packets = [];
        $client = new Client(new Instance($version), 0, static function (string $packet) use (&$packets): void {
            $packets[] = substr($packet, 4);
        });
        $client->reply($result, 0, $binary);
        $process = proc_open([PHP_BINARY, '-r', 'require $argv[1]; (new \\Tests\\Fake\\ResultServer())->serve();', dirname(__DIR__, 2) . '/vendor/autoload.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the response observer.');
        }
        try {
            fwrite($pipes[0], serialize([$version, $result->columns, $packets]));
            fclose($pipes[0]);
            $line = fgets($pipes[1]);
            if ($line === false || preg_match('/\Aready ([0-9]+)\n\z/', $line, $match) !== 1) {
                throw new RuntimeException('The response observer did not become ready.');
            }
            $connection = new mysqli('127.0.0.1', 'root', '', port: (int) $match[1]);
            try {
                if ($binary) {
                    $statement = $connection->prepare('response');
                    if ($statement === false || !$statement->execute()) {
                        throw new RuntimeException('The response observer did not prepare.');
                    }
                    $rows = $statement->get_result();
                } else {
                    $rows = $connection->query('response');
                }
                if (!$rows instanceof mysqli_result) {
                    throw new RuntimeException('The response observer did not produce a result.');
                }

                return $this->decoded($rows);
            } finally {
                $connection->close();
            }
        } finally {
            fclose($pipes[1]);
            proc_terminate($process, 9);
            proc_close($process);
        }
    }

    /**
     * Serves one serialized result transcript in a separate process.
     *
     * @throws RuntimeException When a socket or packet is incomplete
     */
    public function serve(): void
    {
        /** @var array{string, list<\MySqlMemory\Result\ResultColumn>, list<string>} $transcript The private parent-to-child response transcript */
        $transcript = unserialize((string) stream_get_contents(STDIN));
        [$version, $columns, $packets] = $transcript;
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        if ($listener === false) {
            throw new RuntimeException('Could not listen for the response observer.');
        }
        $address = stream_socket_get_name($listener, false);
        if ($address === false) {
            throw new RuntimeException('Could not find the response observer port.');
        }
        $port = explode(':', $address)[1] ?? throw new RuntimeException('Missing response-observer port.');
        fwrite(STDOUT, 'ready ' . $port . "\n");
        $socket = stream_socket_accept($listener, 10);
        if ($socket === false) {
            throw new RuntimeException('The response observer was not connected.');
        }
        try {
            stream_set_timeout($socket, 10);
            $messages = new Messages();
            $this->send($socket, [$messages->handshake($version, 1, random_bytes(20), 2)], 0);
            $this->read($socket);
            $this->send($socket, [$messages->ok(0, 0, 2, 0)], 2);
            while (($request = $this->read($socket)) !== '') {
                $command = ord($request[0]);
                if ($command === 1) {
                    break;
                }
                if ($command === 0x16) {
                    $header = (new PayloadWriter())->integer(0, 1)->integer(1, 4)->integer(count($columns), 2)->integer(0, 2)->integer(0, 1)->integer(0, 2)->payload();
                    $this->send($socket, [$header, ...array_map($messages->column(...), $columns), $messages->eof(0, 2)]);
                } elseif ($command === 0x03 || $command === 0x17) {
                    $this->send($socket, $packets);
                }
            }
        } finally {
            fclose($socket);
            fclose($listener);
        }
    }

    /**
     * @param resource $socket The connected socket
     * @param list<string> $payloads Packet payloads without sequence headers
     */
    public function send($socket, array $payloads, int $sequence = 1): void
    {
        $bytes = '';
        foreach ($payloads as $payload) {
            $bytes .= substr(pack('V', strlen($payload)), 0, 3) . chr($sequence++ & 255) . $payload;
        }
        fwrite($socket, $bytes);
    }

    /**
     * @param resource $socket The connected socket
     * @throws RuntimeException When the peer ends inside a packet
     */
    public function read($socket): string
    {
        $header = fread($socket, 4);
        if ($header === '' || $header === false) {
            return '';
        }
        if (strlen($header) !== 4) {
            throw new RuntimeException('Incomplete response-observer packet header.');
        }
        $length = ord($header[0]) | ord($header[1]) << 8 | ord($header[2]) << 16;
        $body = '';
        while (($remaining = $length - strlen($body)) > 0) {
            $next = fread($socket, $remaining);
            if ($next === false || $next === '') {
                throw new RuntimeException('Incomplete response-observer packet body.');
            }
            $body .= $next;
        }

        return $body;
    }
    /**
     * @return array{columns: list<array<string, int|string>>, rows: list<list<string|null>>}
     */
    public function decoded(mysqli_result $result): array
    {
        $columns = [];
        foreach ($result->fetch_fields() as $field) {
            $columns[] = ['name' => $field->name, 'orgname' => $field->orgname, 'table' => $field->table, 'orgtable' => $field->orgtable, 'db' => $field->db, 'charsetnr' => $field->charsetnr, 'length' => $field->length, 'type' => $field->type, 'flags' => $field->flags, 'decimals' => $field->decimals];
        }
        $rows = array_map(fn (array $row): array => array_values(array_map($this->value(...), $row)), $result->fetch_all(MYSQLI_NUM));
        $result->free();

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Converts binary-protocol numeric values to the text value exposed by direct execution.
     *
     * @throws RuntimeException When the driver supplies a value outside its documented domain
     */
    public function value(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new RuntimeException('Unexpected driver result value: ' . get_debug_type($value));
    }
}
