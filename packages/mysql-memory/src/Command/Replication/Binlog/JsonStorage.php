<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;

/**
 * Reads binary JSON runtime data into the existing typed JSON value model.
 * Offsets belong to their containing document; small inline integers retain
 * their signedness. Bounds and depth checks reject corrupt or cyclic offsets.
 * Source: https://dev.mysql.com/worklog/task/?id=8132 (public binary format).
 *
 * @visibility MySqlMemory
 */
final class JsonStorage
{
    /**
     * Reads a document's type and value.
     */
    public function read(Reader $reader): JsonNode
    {
        return $this->value($reader, $reader->integer(1));
    }

    /**
     * Reads one tagged value; a nested document has its own offsets.
     */
    public function value(Reader $reader, int $type, int $depth = 0): JsonNode
    {
        if ($depth > 100) {
            throw StatementError::SyntaxError->error();
        }

        return match ($type) {
            0, 1, 2, 3 => $this->container($reader, $type, $depth + 1),
            4 => match ($reader->integer(1)) {
                0 => new JsonNode(JsonKind::Null),
                1 => new JsonNode(JsonKind::Boolean, true),
                2 => new JsonNode(JsonKind::Boolean, false),
                default => throw StatementError::SyntaxError->error(),
            },
            5, 7, 9 => new JsonNode(JsonKind::Integer, (string) (new RowImage())->integer($reader, 1 << intdiv($type - 3, 2), false)),
            6, 8, 10 => new JsonNode(JsonKind::Unsigned, sprintf('%u', (new RowImage())->integer($reader, 1 << intdiv($type - 4, 2), true))),
            11 => new JsonNode(JsonKind::Double, (new RowImage())->floating($reader, 8)),
            12 => new JsonNode(JsonKind::String, $reader->bytes($this->length($reader))),
            15 => $this->opaque($reader),
            default => throw StatementError::SyntaxError->error(),
        };
    }

    /**
     * Reads an object or array's entry directory and bounded nested values.
     */
    public function container(Reader $reader, int $type, int $depth): JsonNode
    {
        $width = ($type & 1) === 0 ? 2 : 4;
        $count = $reader->integer($width);
        $size = $reader->integer($width);
        $payload = $reader->bytes($size - 2 * $width);
        $directory = new Reader($payload);
        $keys = [];
        if ($type < 2) {
            for ($i = 0; $i < $count; $i++) {
                $at = $directory->integer($width);
                $length = $directory->integer(2);
                $keys[] = $this->at($payload, $at - 2 * $width)->bytes($length);
            }
        }
        $children = [];
        for ($i = 0; $i < $count; $i++) {
            $tag = $directory->integer(1);
            $entry = $directory->bytes($width);
            $inline = in_array($tag, $width === 4 ? [4, 5, 6, 7, 8] : [4, 5, 6], true);
            $value = $inline ? new Reader($entry) : $this->at($payload, (new Reader($entry))->integer($width) - 2 * $width);
            $children[$keys[$i] ?? $i] = $this->value($value, $tag, $depth);
        }

        return new JsonNode($type < 2 ? JsonKind::Object : JsonKind::Array, $children);
    }

    /**
     * Opens a value at an offset checked against its containing payload.
     */
    public function at(string $payload, int $offset): Reader
    {
        $reader = new Reader($payload);
        $reader->bytes($offset);

        return new Reader($reader->bytes($reader->remaining()));
    }

    /**
     * Reads a string or opaque payload's seven-bit continuation length.
     */
    public function length(Reader $reader): int
    {
        $length = 0;
        for ($shift = 0; $shift < 35; $shift += 7) {
            $byte = $reader->integer(1);
            $length |= ($byte & 127) << $shift;
            if ($byte < 128) {
                return $length;
            }
        }

        throw StatementError::SyntaxError->error();
    }

    /**
     * Preserves SQL decimal, temporal and binary types embedded in JSON.
     */
    public function opaque(Reader $reader): JsonNode
    {
        $type = $reader->integer(1);
        $bytes = $reader->bytes($this->length($reader));
        $value = new Reader($bytes);
        if ($type === 246) {
            return new JsonNode(JsonKind::Decimal, (new DecimalStorage())->read($value, $value->bytes(2)));
        }
        if (in_array($type, [10, 11, 12, 7], true)) {
            $packed = $value->integer(8);
            $magnitude = abs($packed);
            $fraction = $magnitude & 0xffffff;
            $whole = $magnitude >> 24;
            $text = $type === 11
                ? ($packed < 0 ? '-' : '') . sprintf('%02d:%02d:%02d', $whole >> 12, ($whole >> 6) & 63, $whole & 63)
                : (new TemporalStorage())->dateTime($whole);
            $kind = match ($type) {
                10 => JsonKind::Date, 11 => JsonKind::Time, 7 => JsonKind::Timestamp, 12 => JsonKind::DateTime
            };
            return new JsonNode($kind, $type === 10 ? substr($text, 0, 10) : $text . '.' . sprintf('%06d', $fraction));
        }

        return new JsonNode(JsonKind::Opaque, 'base64:type' . $type . ':' . base64_encode($bytes));
    }
}
