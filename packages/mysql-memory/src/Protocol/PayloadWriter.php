<?php

declare(strict_types=1);

namespace MySqlMemory\Protocol;

/**
 * Builds one packet payload of the MySQL client/server protocol.
 *
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_data_types.html.
 *
 * @visibility MySqlMemory
 */
final class PayloadWriter
{
    private string $payload = '';

    /**
     * Appends a fixed-length little-endian integer of one to eight bytes.
     */
    public function integer(int $value, int $bytes): self
    {
        for ($i = 0; $i < $bytes; $i++) {
            $this->payload .= chr($value & 0xFF);
            $value >>= 8;
        }

        return $this;
    }

    /**
     * Appends a length-encoded integer.
     */
    public function lengthEncoded(int $value): self
    {
        if ($value >= 0 && $value < 0xFB) {
            return $this->integer($value, 1);
        }
        if ($value >= 0 && $value < 0x10000) {
            return $this->integer(0xFC, 1)->integer($value, 2);
        }
        if ($value >= 0 && $value < 0x1000000) {
            return $this->integer(0xFD, 1)->integer($value, 3);
        }

        return $this->integer(0xFE, 1)->integer($value, 8);
    }

    /**
     * Appends a string prefixed by its length-encoded length.
     */
    public function lengthEncodedString(string $text): self
    {
        return $this->lengthEncoded(strlen($text))->bytes($text);
    }

    /**
     * Appends a string and a NUL terminator.
     */
    public function nulTerminated(string $text): self
    {
        $this->payload .= $text . "\0";

        return $this;
    }

    /**
     * Appends bytes as they are.
     */
    public function bytes(string $bytes): self
    {
        $this->payload .= $bytes;

        return $this;
    }

    /**
     * Answers the payload built so far.
     */
    public function payload(): string
    {
        return $this->payload;
    }
}
