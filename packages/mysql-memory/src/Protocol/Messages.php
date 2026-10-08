<?php

declare(strict_types=1);

namespace MySqlMemory\Protocol;

use MySqlMemory\Result\ResultColumn;

/**
 * Builds the payloads the server sends: the handshake, OK, ERR and EOF packets, and column definitions.
 *
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_packets.html.
 *
 * @visibility MySqlMemory
 */
final class Messages
{
    /**
     * The capabilities the server offers.
     */
    public const CAPABILITIES = Capability::LONG_PASSWORD | Capability::FOUND_ROWS | Capability::LONG_FLAG | Capability::CONNECT_WITH_DB
        | Capability::PROTOCOL_41 | Capability::TRANSACTIONS | Capability::SECURE_CONNECTION | Capability::MULTI_STATEMENTS
        | Capability::MULTI_RESULTS | Capability::PS_MULTI_RESULTS | Capability::PLUGIN_AUTH | Capability::CONNECT_ATTRS
        | Capability::PLUGIN_AUTH_LENENC_CLIENT_DATA;

    /**
     * Builds the initial handshake (protocol version 10), naming the collation of the server's connections.
     */
    public function handshake(string $version, int $connection, string $scramble, int $status, int $collation = 255): string
    {
        return (new PayloadWriter())
            ->integer(10, 1)
            ->nulTerminated($version)
            ->integer($connection, 4)
            ->bytes(substr($scramble, 0, 8))
            ->integer(0, 1)
            ->integer(self::CAPABILITIES & 0xFFFF, 2)
            ->integer($collation & 0xFF, 1)
            ->integer($status, 2)
            ->integer(self::CAPABILITIES >> 16, 2)
            ->integer(21, 1)
            ->bytes(str_repeat("\0", 10))
            ->bytes(substr($scramble, 8, 12) . "\0")
            ->nulTerminated('mysql_native_password')
            ->payload();
    }

    /**
     * Builds an OK packet.
     */
    public function ok(int $affectedRows, int $lastInsertId, int $status, int $warnings, string $info = ''): string
    {
        $writer = (new PayloadWriter())->integer(0, 1)->lengthEncoded($affectedRows)->lengthEncoded($lastInsertId)->integer($status, 2)->integer($warnings, 2);

        return $info === '' ? $writer->payload() : $writer->lengthEncodedString($info)->payload();
    }

    /**
     * Builds an ERR packet.
     */
    public function error(int $code, string $state, string $message): string
    {
        return (new PayloadWriter())->integer(0xFF, 1)->integer($code, 2)->bytes('#' . str_pad(substr($state, 0, 5), 5, '0'))->bytes($message)->payload();
    }

    /**
     * Builds an EOF packet.
     */
    public function eof(int $warnings, int $status): string
    {
        return (new PayloadWriter())->integer(0xFE, 1)->integer($warnings, 2)->integer($status, 2)->payload();
    }

    /**
     * Builds the column count that starts a result set.
     */
    public function columnCount(int $count): string
    {
        return (new PayloadWriter())->lengthEncoded($count)->payload();
    }

    /**
     * Builds a column definition (protocol 4.1).
     */
    public function column(ResultColumn $column): string
    {
        return (new PayloadWriter())
            ->lengthEncodedString('def')
            ->lengthEncodedString($column->schema)
            ->lengthEncodedString($column->table)
            ->lengthEncodedString($column->originalTable)
            ->lengthEncodedString($column->name)
            ->lengthEncodedString($column->originalName)
            ->lengthEncoded(0x0C)
            ->integer($column->charset, 2)
            ->integer(min($column->length, 0xFFFFFFFF), 4)
            ->integer($column->type->value, 1)
            ->integer($column->flags & 0xFFFF, 2)
            ->integer($column->decimals, 1)
            ->integer(0, 2)
            ->payload();
    }

    /**
     * Builds a row of the text protocol.
     *
     * @param list<string|null> $values
     */
    public function textRow(array $values): string
    {
        $writer = new PayloadWriter();
        foreach ($values as $value) {
            if ($value === null) {
                $writer->integer(0xFB, 1);
            } else {
                $writer->lengthEncodedString($value);
            }
        }

        return $writer->payload();
    }
}
