<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Value\Json\JsonEdit;
use MySqlMemory\Value\Json\JsonLegKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use MySqlMemory\Value\Json\JsonSyntax;

/**
 * Applies the binary log's ordered JSON replace/insert/remove operations.
 * This interprets runtime JSON paths, without producing or parsing SQL.
 * Source: https://dev.mysql.com/blog-archive/efficient-json-replication-in-mysql-8-0/.
 *
 * @visibility MySqlMemory
 */
final class JsonChanges
{
    /**
     * Applies each bounded diff to the previous stored JSON document.
     */
    public function apply(Reader $reader, ?string $previous): string
    {
        if ($previous === null) {
            throw StatementError::UnknownError->error();
        }
        $node = JsonNode::load($previous);
        $changes = new Reader($reader->bytes($reader->integer(4)));
        while ($changes->remaining() !== 0) {
            $operation = $changes->integer(1);
            $path = $this->path($changes->bytes($changes->count()));
            $value = $operation === 2 ? null : (new JsonStorage())->read(new Reader($changes->bytes($changes->count())));
            $node = $this->change($node, $operation, $path, $value);
        }

        return $node->store();
    }

    /**
     * Validates a runtime path in a diff; malformed event data is an SQL error.
     *
     * @throws \MySqlMemory\Error\SqlError When the encoded path is malformed
     */
    public function path(string $text): JsonPath
    {
        try {
            $path = JsonPath::parse($text);
        } catch (JsonSyntax $error) {
            throw new \MySqlMemory\Error\SqlError(StatementError::SyntaxError, StatementError::SyntaxError->message(), $error);
        }
        if ($path->wild() || $path->legs === []) {
            throw StatementError::SyntaxError->error();
        }

        return $path;
    }

    /**
     * Applies one operation, preserving the difference between inserting an
     * object member and inserting before an existing array position.
     */
    public function change(JsonNode $node, int $operation, JsonPath $path, ?JsonNode $value): JsonNode
    {
        $last = $path->legs[count($path->legs) - 1];
        $value ??= new JsonNode(\MySqlMemory\Value\Json\JsonKind::Null);

        return match ($operation) {
            0 => JsonEdit::put($node, $path, $value, false, true),
            1 => $last->kind === JsonLegKind::Cell ? JsonEdit::insert($node, $path, $value) : JsonEdit::put($node, $path, $value, true, false),
            2 => JsonEdit::remove($node, $path),
            default => throw StatementError::SyntaxError->error(),
        };
    }
}
