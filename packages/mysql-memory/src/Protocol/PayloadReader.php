<?php

declare(strict_types=1);

namespace MySqlMemory\Protocol;

/**
 * Reads the fields of one packet payload of the MySQL client/server protocol from the start.
 *
 * Integers are little-endian; a length-encoded integer starts with one byte below 0xFB, or with
 * 0xFC, 0xFD or 0xFE followed by two, three or eight bytes. Reading past the end raises
 * MalformedPacket.
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_data_types.html.
 *
 * @visibility MySqlMemory
 */
final class PayloadReader
{
    private int $offset = 0;

    /**
     * @param string $payload The payload bytes, without the four-byte packet header
     */
    public function __construct(public readonly string $payload)
    {
    }

    /**
     * Tells whether bytes remain to be read.
     */
    public function remaining(): int
    {
        return strlen($this->payload) - $this->offset;
    }

    /**
     * Reads a fixed-length unsigned integer of one to eight bytes.
     */
    public function integer(int $bytes): int
    {
        $raw = $this->bytes($bytes);
        $value = 0;
        for ($i = $bytes - 1; $i >= 0; $i--) {
            $value = ($value << 8) | ord($raw[$i]);
        }

        return $value;
    }

    /**
     * Reads a length-encoded integer, or null for the NULL marker 0xFB.
     */
    public function lengthEncoded(): ?int
    {
        $first = $this->integer(1);

        return match (true) {
            $first < 0xFB => $first,
            $first === 0xFB => null,
            $first === 0xFC => $this->integer(2),
            $first === 0xFD => $this->integer(3),
            $first === 0xFE => $this->integer(8),
            default => throw new MalformedPacket('An integer cannot start with 0xFF.'),
        };
    }

    /**
     * Reads a string prefixed by its length-encoded length.
     */
    public function lengthEncodedString(): string
    {
        return $this->bytes($this->lengthEncoded() ?? 0);
    }

    /**
     * Reads a string terminated by a NUL byte, consuming the terminator.
     */
    public function nulTerminated(): string
    {
        $end = strpos($this->payload, "\0", $this->offset);
        if ($end === false) {
            throw new MalformedPacket('A NUL-terminated string has no terminator.');
        }
        $text = substr($this->payload, $this->offset, $end - $this->offset);
        $this->offset = $end + 1;

        return $text;
    }

    /**
     * Reads a number of bytes.
     */
    public function bytes(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->payload)) {
            throw new MalformedPacket('The packet ends before the field.');
        }
        $bytes = substr($this->payload, $this->offset, $length);
        $this->offset += $length;

        return $bytes;
    }

    /**
     * Reads every byte that remains.
     */
    public function rest(): string
    {
        return $this->bytes($this->remaining());
    }
}
