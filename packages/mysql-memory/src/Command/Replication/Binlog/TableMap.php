<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

/**
 * A source table's identity and storage types from a runtime table-map event.
 * Optional metadata does not change the encoded row layout. The destination's
 * catalog supplies signedness and ENUM/SET member names during application.
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_replication_binlog_event.html.
 *
 * @visibility MySqlMemory
 */
final class TableMap
{
    /**
     * @param list<int> $types Source column type codes
     * @param list<string> $metadata Type-specific storage metadata in column order
     */
    public function __construct(public readonly int $id, public readonly string $schema, public readonly string $table, public readonly array $types, public readonly array $metadata)
    {
    }

    /**
     * Reads the identity, type list and per-column storage widths.
     */
    public static function read(string $event, Format $format): self
    {
        $reader = $format->body($event);
        $id = $reader->integer($format->postHeader(19) === 6 ? 4 : 6);
        $reader->bytes(2);
        $schema = $reader->name();
        $table = $reader->name();
        $count = $reader->count();
        $types = array_map('ord', str_split($reader->bytes($count)));
        $metadata = new Reader($reader->bytes($reader->count()));
        $fields = array_map(static fn (int $type): string => $metadata->bytes(match ($type) {
            4, 5, 17, 18, 19, 242, 245, 249, 250, 251, 252, 255 => 1,
            15, 16, 246, 247, 248, 253, 254 => 2,
            default => 0,
        }), $types);
        $reader->bytes(intdiv($count + 7, 8));

        return new self($id, $schema, $table, $types, $fields);
    }
}
