<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use Closure;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Instance;
use MySqlMemory\Protocol\Capability;
use MySqlMemory\Protocol\MalformedPacket;
use MySqlMemory\Protocol\Messages;
use MySqlMemory\Protocol\PayloadReader;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use Throwable;

/**
 * One client connection: the handshake, then the commands of the client/server protocol, each answered from a session.
 *
 * Any password is accepted for any user. Statements of a COM_QUERY run in order; each result
 * but the last carries SERVER_MORE_RESULTS_EXISTS, and the first error ends them.
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_command_phase.html.
 *
 * @visibility MySqlMemory
 */
final class Client
{
    private string $buffer = '';

    private int $sequence = 0;

    private ?Session $session = null;

    private int $capabilities = 0;

    /**
     * Builds the payloads the connection sends.
     */
    public readonly Messages $messages;

    /**
     * The prepared statements of the connection.
     */
    public readonly Statements $statements;

    /**
     * @param Instance $instance The server
     * @param int $id The connection id
     * @param Closure $send Sends bytes to the client: fn (string): void
     * @param string $host The address the client connects from
     */
    public function __construct(public readonly Instance $instance, public readonly int $id, public readonly Closure $send, public readonly string $host = 'localhost')
    {
        $this->messages = new Messages();
        $this->statements = new Statements($this);
    }

    /**
     * Sends the initial handshake.
     */
    public function greet(): void
    {
        $this->sequence = 0;
        $connection = $this->instance->catalog->find('collation_connection');
        $collation = $connection === null ? null : \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named((string) $this->instance->globals->value($connection));
        $this->packet($this->messages->handshake($this->instance->version, $this->id, random_bytes(20), 2, $collation->id ?? 255));
    }

    /**
     * Takes bytes from the client and answers every complete packet; answers false when the connection ends.
     */
    public function receive(string $bytes): bool
    {
        $this->buffer .= $bytes;
        while (strlen($this->buffer) >= 4) {
            $length = ord($this->buffer[0]) | (ord($this->buffer[1]) << 8) | (ord($this->buffer[2]) << 16);
            if (strlen($this->buffer) < 4 + $length) {
                break;
            }
            $this->sequence = (ord($this->buffer[3]) + 1) & 0xFF;
            $payload = substr($this->buffer, 4, $length);
            $this->buffer = substr($this->buffer, 4 + $length);
            try {
                if (!$this->handle($payload)) {
                    return false;
                }
            } catch (MalformedPacket $failure) {
                $this->packet($this->messages->error(1047, '08S01', StatementError::UnknownCommand->message()));

                return false;
            } catch (Throwable $failure) {
                $this->packet($this->messages->error(1105, 'HY000', 'mysql-memory internal error: ' . $failure::class . ': ' . $failure->getMessage()));
            }
        }

        return true;
    }

    /**
     * Answers one packet; answers false when the client quits.
     *
     * @throws MalformedPacket When the packet does not follow the protocol
     */
    public function handle(string $payload): bool
    {
        if ($this->session === null) {
            return $this->authenticate(new PayloadReader($payload));
        }
        $reader = new PayloadReader($payload);
        $command = $reader->integer(1);

        return match ($command) {
            0x01 => false,
            0x02 => $this->initDatabase($reader->rest()),
            0x03 => $this->query($reader->rest()),
            0x04 => $this->send($this->messages->eof(0, $this->status())),
            0x0E, 0x1F => $this->ping($command === 0x1F),
            0x1B => $this->send($this->messages->eof(0, $this->status())),
            0x09 => $this->send('Uptime: 1  Threads: 1  Questions: 0  Slow queries: 0  Opens: 0  Flush tables: 0  Open tables: 0  Queries per second avg: 0.000'),
            0x16, 0x17, 0x18, 0x19, 0x1A, 0x1C => $this->statements->handle($command, $reader),
            default => $this->send($this->messages->error(1047, '08S01', StatementError::UnknownCommand->message())),
        };
    }

    /**
     * Reads the handshake response and opens the session.
     *
     * @throws MalformedPacket When the handshake response ends before a field
     */
    public function authenticate(PayloadReader $reader): bool
    {
        $this->capabilities = $reader->integer(4);
        $reader->integer(4);
        $reader->integer(1);
        $reader->bytes(23);
        $user = $reader->nulTerminated();
        if (($this->capabilities & Capability::PLUGIN_AUTH_LENENC_CLIENT_DATA) !== 0) {
            $reader->lengthEncodedString();
        } else {
            $reader->bytes($reader->integer(1));
        }
        $database = null;
        if (($this->capabilities & Capability::CONNECT_WITH_DB) !== 0 && $reader->remaining() > 0) {
            $database = $reader->nulTerminated();
            $database = $database === '' ? null : $database;
        }
        try {
            $this->session = $this->instance->connect($user, $this->instance->clientHost ?? $this->host, $database);
        } catch (SqlError $error) {
            $this->packet($this->messages->error($error->getCode(), $error->sqlState(), $error->getMessage()));

            return false;
        }

        return $this->send($this->messages->ok(0, 0, $this->status(), 0));
    }

    /**
     * Answers the session of the connection.
     */
    public function session(): Session
    {
        assert($this->session !== null);

        return $this->session;
    }

    /**
     * Answers the server status flags of the session.
     */
    public function status(): int
    {
        if ($this->session === null) {
            return 2;
        }
        $autocommit = in_array(strtoupper((string) $this->session->variables->read('autocommit')), ['ON', '1'], true) ? 2 : 0;

        return $autocommit | ($this->session->transaction->open ? 1 : 0) | ($this->session->modes()->has('NO_BACKSLASH_ESCAPES') ? 512 : 0);
    }

    /**
     * Answers COM_INIT_DB.
     */
    public function initDatabase(string $database): bool
    {
        try {
            $this->session()->use($database);
        } catch (SqlError $error) {
            return $this->send($this->messages->error($error->getCode(), $error->sqlState(), $error->getMessage()));
        }

        return $this->send($this->messages->ok(0, 0, $this->status(), 0));
    }

    /**
     * Answers COM_PING and COM_RESET_CONNECTION.
     */
    public function ping(bool $reset): bool
    {
        if ($reset) {
            $session = $this->session();
            $this->session = $this->instance->connect($session->user, $session->host, $session->variables->database === '' ? null : $session->variables->database);
        }

        return $this->send($this->messages->ok(0, 0, $this->status(), 0));
    }

    /**
     * Answers COM_QUERY.
     */
    public function query(string $sql): bool
    {
        $answers = $this->session()->run($sql);
        foreach ($answers as $index => $answer) {
            $more = $index < count($answers) - 1 ? 8 : 0;
            if ($answer instanceof SqlError) {
                return $this->send($this->messages->error($answer->getCode(), $answer->sqlState(), $answer->getMessage()));
            }
            $this->reply($answer, $more, false);
        }

        return true;
    }

    /**
     * Sends a reply in the text or binary protocol.
     */
    public function reply(Reply $reply, int $more, bool $binary): void
    {
        $status = $this->status() | $more;
        if ($reply instanceof Completion) {
            $affected = $reply->affectedRows;
            $this->packet($this->messages->ok($affected, $reply->lastInsertId, $status, $reply->warnings, $reply->info));

            return;
        }
        assert($reply instanceof ResultSet);
        $this->packet($this->messages->columnCount(count($reply->columns)));
        foreach ($reply->columns as $column) {
            $this->packet($this->messages->column($column));
        }
        $this->packet($this->messages->eof(0, $status));
        foreach ($reply->rows as $row) {
            $this->packet($binary ? (new \MySqlMemory\Protocol\Binary())->row($reply->columns, $row) : $this->messages->textRow($row));
        }
        $this->packet($this->messages->eof($reply->warnings, $status));
    }

    /**
     * Sends one packet and answers true.
     */
    public function send(string $payload): bool
    {
        $this->packet($payload);

        return true;
    }

    /**
     * Sends one packet with the next sequence number.
     */
    public function packet(string $payload): void
    {
        $length = strlen($payload);
        ($this->send)(chr($length & 0xFF) . chr(($length >> 8) & 0xFF) . chr(($length >> 16) & 0xFF) . chr($this->sequence) . $payload);
        $this->sequence = ($this->sequence + 1) & 0xFF;
    }
}
