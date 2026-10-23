<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Typing\Domain;

/**
 * Decodes the storage values carried by row events, independently of SQL text.
 * Source types determine byte consumption; destination domains determine integer
 * signedness and member names. Rows use their own null bitmap over present columns.
 *
 * @visibility MySqlMemory
 */
final class RowImage
{
    private const INTEGER_WIDTHS = [1 => 1, 2 => 2, 3 => 4, 8 => 8, 9 => 3];

    /**
     * @param list<\MySqlMemory\Dictionary\ColumnDefinition> $columns Destination columns
     * @param array<int, int|float|string|null> $previous Existing row for partial JSON images
     * @return array<int, int|float|string|null> RowImage indexed by source column position
     */
    public function row(Reader $reader, TableMap $map, string $present, array $columns, string $partial = '', array $previous = []): array
    {
        $selected = array_keys(array_filter($map->types, static fn (int $position): bool => (ord($present[intdiv($position, 8)]) & (1 << ($position % 8))) !== 0, ARRAY_FILTER_USE_KEY));
        $nulls = $reader->bytes(intdiv(count($selected) + 7, 8));
        $jsonPositions = array_keys(array_filter($map->types, static fn (int $type): bool => $type === 245));
        $values = [];
        foreach ($selected as $index => $position) {
            if ((ord($nulls[intdiv($index, 8)]) & (1 << ($index % 8))) !== 0) {
                if (isset($columns[$position])) {
                    $values[$position] = null;
                }
                continue;
            }
            $domain = $columns[$position]->domain ?? Domain::null();
            $jsonIndex = array_search($position, $jsonPositions, true);
            $isPartial = $partial !== '' && $jsonIndex !== false && (ord($partial[intdiv($jsonIndex, 8)]) & (1 << ($jsonIndex % 8))) !== 0;
            if ($isPartial && !isset($columns[$position])) {
                $reader->bytes($reader->integer(4));
                continue;
            }
            $old = $previous[$position] ?? null;
            $values[$position] = $isPartial ? (new JsonChanges())->apply($reader, is_string($old) ? $old : null) : $this->value($reader, $map->types[$position], $map->metadata[$position], $domain);
        }

        return array_intersect_key($values, $columns);
    }

    /**
     * Reads one source storage value using its table-map metadata.
     */
    public function value(Reader $reader, int $type, string $metadata, Domain $domain): int|float|string|null
    {
        if (isset(self::INTEGER_WIDTHS[$type])) {
            return $this->integer($reader, self::INTEGER_WIDTHS[$type], $domain->unsigned);
        }

        return match ($type) {
            4 => $this->floating($reader, 4),
            5 => $this->floating($reader, 8),
            6 => null,
            13 => $this->year($reader),
            15, 253 => $reader->bytes($reader->integer((new Reader($metadata))->integer(2) > 255 ? 2 : 1)),
            254 => $this->string($reader, $metadata, $domain),
            16 => $reader->bytes(ord($metadata[1]) + intdiv(ord($metadata[0]) + 7, 8)),
            10, 14 => (new TemporalStorage())->date($reader),
            7, 11, 12 => (new TemporalStorage())->legacy($reader, $type),
            17, 18, 19 => (new TemporalStorage())->read($reader, $type, $metadata),
            246 => (new DecimalStorage())->read($reader, $metadata),
            245 => (new JsonStorage())->read(new Reader($reader->bytes($reader->integer(ord($metadata[0])))))->store(),
            242, 249, 250, 251, 252, 255 => $reader->bytes($reader->integer((new Reader($metadata))->integer(1))),
            default => throw StatementError::NotSupportedYet->error('binary log storage type ' . $type),
        };
    }

    /**
     * Reads a stored integer, preserving unsigned 64-bit values as bit patterns.
     */
    public function integer(Reader $reader, int $length, bool $unsigned): int
    {
        $value = $reader->integer($length);

        return $unsigned || $length === 8 || $value < (1 << ($length * 8 - 1)) ? $value : $value - (1 << ($length * 8));
    }

    /**
     * Reads an IEEE 754 floating value.
     */
    public function floating(Reader $reader, int $length): float
    {
        $value = unpack($length === 4 ? 'g' : 'e', $reader->bytes($length));
        \SqlSemantics\Diagnostic\Check::invariant($value !== false && is_float($value[1]), 'A fixed-size float payload decodes to a float.');

        return $value[1];
    }

    /**
     * Reads the stored year offset, preserving the zero year.
     */
    public function year(Reader $reader): int
    {
        $year = $reader->integer(1);

        return $year === 0 ? 0 : 1900 + $year;
    }

    /**
     * Reads CHAR/BINARY or the ENUM/SET type embedded in a STRING table-map entry.
     */
    public function string(Reader $reader, string $metadata, Domain $domain): string
    {
        $meta = new Reader($metadata);
        $kind = $meta->integer(1);
        $width = $meta->integer(1);
        if ($kind === 247) {
            return $domain->members[$reader->integer($width) - 1] ?? '';
        }
        if ($kind === 248) {
            $bits = $reader->integer($width);
            return implode(',', array_filter($domain->members, static fn (int $index): bool => ($bits & (1 << $index)) !== 0, ARRAY_FILTER_USE_KEY));
        }
        $width |= (($kind & 0x30) ^ 0x30) << 4;

        $value = $reader->bytes($reader->integer($width > 255 ? 2 : 1));

        return $domain->collation === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary() ? str_pad($value, $width, "\0") : $value;
    }
}
