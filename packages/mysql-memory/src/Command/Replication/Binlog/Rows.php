<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Writer;

/**
 * Applies decoded row images to the destination catalog and transaction journal.
 * The event's table map supplies the source identity; no SQL is generated or parsed.
 * Row application bypasses SQL triggers, as native row-based replay does.
 *
 * @visibility MySqlMemory
 */
final class Rows
{
    /**
     * Applies each row image and releases maps at a statement-end flag.
     *
     * @throws \MySqlMemory\Error\SqlError When the source image or destination rejects an operation
     */
    public function apply(string $event, int $type, Format $format, Session $session, Context $context): void
    {
        $reader = $format->body($event);
        $id = $reader->integer($format->postHeader($type) === 6 ? 4 : 6);
        $flags = $reader->integer(2);
        if ($format->postHeader($type) >= 10) {
            $reader->bytes($reader->integer(2) - 2);
        }
        $map = $session->binlog->tables[$id] ?? throw StatementError::SyntaxError->error();
        $count = $reader->count();
        if ($count !== count($map->types)) {
            throw StatementError::SyntaxError->error();
        }
        $before = $reader->bytes(intdiv($count + 7, 8));
        $after = in_array($type, [31, 39], true) ? $reader->bytes(intdiv($count + 7, 8)) : '';
        $table = $this->table($map, $session);
        $this->compatible($map, $table);
        $session->transaction->touch($table);
        while ($reader->remaining() > 0) {
            $values = (new RowImage())->row($reader, $map, $before, $table->definition->columns);
            if ($type === 30) {
                $this->insert($values, $table, $session, $context, ($flags & 2) === 0);
            } else {
                $number = $this->find($values, $table);
                if (in_array($type, [31, 39], true)) {
                    $partial = $type === 39 ? $this->partial($reader, $map) : '';
                    $updated = (new RowImage())->row($reader, $map, $after, $table->definition->columns, $partial, $table->data->rows[$number]);
                    $this->update($number, $updated, $table, $session, $context, ($flags & 2) === 0);
                } else {
                    if (($flags & 2) === 0) {
                        (new \MySqlMemory\Storage\References($session, $context))->deleting($table, $table->data->rows[$number]);
                    }
                    $session->transaction->write($table, $number);
                    $table->data->delete($number);
                }
            }
        }
        if (($flags & 1) !== 0) {
            $session->binlog->tables = [];
        }
    }

    /**
     * Reads the shared image that precedes a partial-update after image.
     */
    public function partial(Reader $reader, TableMap $map): string
    {
        $options = $reader->count();
        if (($options & ~1) !== 0) {
            throw StatementError::SyntaxError->error();
        }
        $count = count(array_filter($map->types, static fn (int $type): bool => $type === 245));

        return $options === 0 ? '' : $reader->bytes(intdiv($count + 7, 8));
    }

    /**
     * Resolves a runtime table-map identity against the destination catalog.
     */
    public function table(TableMap $map, Session $session): StoredTable
    {
        $dictionary = $session->instance->dictionary;
        if (version_compare($session->instance->version, '8.0', '>=') && $dictionary->schema($map->schema) === null) {
            throw QueryError::BadDatabase->error($map->schema);
        }

        return $dictionary->table($map->schema, $map->table) ?? throw QueryError::NoSuchTable->error($map->schema, $map->table);
    }

    /**
     * Checks the source storage widths before reading row images. Default native
     * replication conversion settings refuse both wider and narrower VARCHARs.
     */
    public function compatible(TableMap $map, StoredTable $table): void
    {
        foreach ($map->types as $position => $type) {
            $column = $table->definition->columns[$position] ?? null;
            if ($column === null) {
                continue;
            }
            if (in_array($type, [15, 253], true) && (new Reader($map->metadata[$position]))->integer(2) !== $column->domain->byteLength()) {
                throw StatementError::UnknownError->error();
            }
        }
    }

    /**
     * Finds an existing row by the present before-image columns.
     *
     * @param array<int, int|float|string|null> $values Before-image values
     *
     * @throws \MySqlMemory\Error\SqlError When no row matches the before image
     */
    public function find(array $values, StoredTable $table): int
    {
        $identity = $table->definition->clusterColumns();
        if ($identity !== [] && array_diff($identity, array_keys($values)) === []) {
            $values = array_intersect_key($values, array_flip($identity));
        }
        foreach ($table->data->rows as $number => $row) {
            $matches = true;
            foreach ($values as $position => $value) {
                if ($row[$position] !== $value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $number;
            }
        }

        $message = QueryError::RecordNotFound->message($table->definition->name);
        $following = $this->indexCandidate($values, $table) ? [[1032, $message]] : [];
        throw new \MySqlMemory\Error\SqlError(QueryError::RecordNotFound, $message, following: $following);
    }

    /**
     * Detects a nullable unique lookup whose candidate failed the full image
     * comparison. Native replay reports both failed lookup attempts.
     *
     * @param array<int, int|float|string|null> $values Before-image columns
     */
    public function indexCandidate(array $values, StoredTable $table): bool
    {
        foreach ($table->definition->keys as $key) {
            if (!$key->unique() || array_diff($key->columns, array_keys($values)) !== []) {
                continue;
            }
            $wanted = array_intersect_key($values, array_flip($key->columns));
            if (in_array(null, $wanted, true)) {
                continue;
            }
            foreach ($table->data->rows as $row) {
                if (array_intersect_key($row, $wanted) === $wanted) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Inserts a row image, preserving ordinary uniqueness and transaction state.
     *
     * @param array<int, int|float|string|null> $values Present source columns
     */
    public function insert(array $values, StoredTable $table, Session $session, Context $context, bool $foreignKeys = true): void
    {
        $row = array_map(static fn ($column) => $column->default->value, $table->definition->columns);
        $row = array_values(array_replace($row, $values));
        $writer = new Writer($table, $context);
        $row = $writer->generate($row, new \MySqlMemory\Storage\Store($context, table: $table->definition->name));
        $violated = $writer->violated($row);
        if ($violated !== null) {
            throw \MySqlMemory\Error\Family\DataError::CheckConstraintViolated->error($violated->name);
        }
        $conflict = $writer->conflict($row);
        if ($conflict !== null) {
            throw $writer->duplicate($row, $conflict[1]);
        }
        if ($foreignKeys) {
            $references = new \MySqlMemory\Storage\References($session, $context);
            $orphan = $references->orphan($table, $row);
            if ($orphan !== null) {
                throw $references->violation($table, $orphan, false);
            }
        }
        [$row] = $writer->autoIncrement($row, true);
        $session->transaction->write($table, $table->data->nextRow);
        $table->data->insert($row);
    }

    /**
     * Replaces the columns carried by an update's after image.
     *
     * @param array<int, int|float|string|null> $values Present source columns
     */
    public function update(int $number, array $values, StoredTable $table, Session $session, Context $context, bool $foreignKeys = true): void
    {
        $row = array_values(array_replace($table->data->rows[$number], $values));
        $writer = new Writer($table, $context);
        $row = $writer->generate($row, new \MySqlMemory\Storage\Store($context, table: $table->definition->name));
        $violated = $writer->violated($row);
        if ($violated !== null) {
            throw \MySqlMemory\Error\Family\DataError::CheckConstraintViolated->error($violated->name);
        }
        $conflict = $writer->conflict($row, $number);
        if ($conflict !== null) {
            throw $writer->duplicate($row, $conflict[1]);
        }
        if ($foreignKeys) {
            $references = new \MySqlMemory\Storage\References($session, $context);
            $old = $table->data->rows[$number];
            $orphan = $references->orphan($table, $row, $old);
            if ($orphan !== null) {
                throw $references->violation($table, $orphan, false);
            }
            $references->updating($table, $old, $row);
        }
        $session->transaction->write($table, $number);
        $table->data->update($number, $row);
    }
}
