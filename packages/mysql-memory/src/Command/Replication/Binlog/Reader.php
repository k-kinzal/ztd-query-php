<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

use MySqlMemory\Error\Family\StatementError;

/**
 * Reads bounded fields from a binary log event supplied as runtime data.
 * Integers are little-endian unless their field explicitly requests otherwise.
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_replication_binlog_event.html.
 *
 * @visibility MySqlMemory
 */
final class Reader
{
    private int $offset = 0;

    /**
     * @param string $payload Bounded runtime event bytes
     */
    public function __construct(public readonly string $payload)
    {
    }

    /**
     * Answers the number of unread bytes.
     */
    public function remaining(): int
    {
        return strlen($this->payload) - $this->offset;
    }

    /**
     * Reads bytes or reports the malformed event as SQL syntax error 1149.
     */
    public function bytes(int $length): string
    {
        if ($length < 0 || $length > $this->remaining()) {
            throw StatementError::SyntaxError->error();
        }
        $value = substr($this->payload, $this->offset, $length);
        $this->offset += $length;

        return $value;
    }

    /**
     * Reads an unsigned integer with an explicit byte order.
     */
    public function integer(int $length, bool $bigEndian = false): int
    {
        $bytes = $this->bytes($length);
        $bytes = $bigEndian ? $bytes : strrev($bytes);
        $value = 0;
        for ($i = 0; $i < $length; $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }

        return $value;
    }

    /**
     * Reads a length-encoded count used by table maps and row bitmaps.
     */
    public function count(): int
    {
        $prefix = $this->integer(1);

        return match (true) {
            $prefix < 251 => $prefix,
            $prefix === 252 => $this->integer(2),
            $prefix === 253 => $this->integer(3),
            $prefix === 254 => $this->integer(8),
            default => throw StatementError::SyntaxError->error(),
        };
    }

    /**
     * Reads a one-byte length followed by a NUL-terminated name.
     */
    public function name(): string
    {
        $name = $this->bytes($this->integer(1));
        if ($this->bytes(1) !== "\0") {
            throw StatementError::SyntaxError->error();
        }

        return $name;
    }
}
