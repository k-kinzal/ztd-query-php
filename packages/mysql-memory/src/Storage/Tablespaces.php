<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Keeps detached InnoDB files separate from table definitions and open handles.
 *
 * DISCARD retains the definition, removes the stored rows, and makes subsequent
 * scans and writes fail only when they reach a detached partition. A cold open
 * reports its own warning before execution; an impossible predicate need not
 * read storage. Verified on MySQL 5.6.51, 5.7.44 and 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-table-import.html.
 *
 * @visibility MySqlMemory
 */
final class Tablespaces
{
    /**
     * Answers whether a scan reaches a detached file.
     *
     * @param list<int>|null $selected Selected partition positions, or every partition
     */
    public function missing(StoredTable $table, ?array $selected = null): bool
    {
        if ($selected === null) {
            return $table->discardedTablespaces !== [];
        }
        foreach ($selected as $position) {
            $name = $table->definition->partitioning?->partitions[$position]->name ?? '';
            if (isset($table->discardedTablespaces[strtolower($name)])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reports the engine warning when a detached table gets a new open handle.
     */
    public function opened(StoredTable $table, Session $session): void
    {
        $definition = $table->definition;
        if ($this->missing($table) && !in_array([$definition->schema, $definition->name], $session->instance->dictionary->cache->names(), true)) {
            $session->diagnostics->warning(SchemaError::TablespaceDiscarded, 'InnoDB: ' . $this->message($definition->name, $session->settings()->release()));
        }
    }

    /**
     * Refuses a scan of detached storage, after planning and constant predicates.
     *
     * @param list<int>|null $selected Selected partition positions, or every partition
     * @throws SqlError When a selected file was discarded
     */
    public function read(StoredTable $table, GrammarRelease $release, ?array $selected = null): void
    {
        if ($this->missing($table, $selected)) {
            $definition = $table->definition;
            throw new SqlError(SchemaError::TablespaceDiscarded, $this->message($definition->name, $release), following: [[1146, QueryError::NoSuchTable->message($definition->schema, $definition->name)]]);
        }
    }

    /**
     * Refuses the destination file after row conversion and partition selection.
     *
     * @param list<int|float|string|null> $row The converted destination row
     * @throws SqlError When the row's file was discarded
     */
    public function write(StoredTable $table, array $row, Context $context): void
    {
        if ($table->discardedTablespaces === []) {
            return;
        }
        $definition = $table->definition;
        $selected = $definition->partitioning === null ? null : [(new Partitions($definition, $definition->partitioning, $context))->locate($row)];
        if (!$this->missing($table, $selected)) {
            return;
        }
        $following = $context->modes->release === GrammarRelease::MySql5651
            ? [1146, QueryError::NoSuchTable->message($definition->schema, $definition->name)]
            : [1812, SchemaError::TablespaceUnavailable->message('`' . $definition->schema . '`.`' . $definition->name . '`')];
        throw new SqlError(SchemaError::TablespaceDiscarded, $this->message($definition->name, $context->modes->release), following: [$following]);
    }

    /**
     * Removes a file's rows while keeping every other partition and its identity.
     *
     * @param non-empty-list<int|null> $selected Detached partition positions, or null for an unpartitioned table
     */
    public function discard(StoredTable $table, array $selected, Context $context): void
    {
        $partitioning = $table->definition->partitioning;
        foreach ($selected as $partition) {
            $name = $partition === null ? '' : ($partitioning?->partitions[$partition]->name ?? '');
            $table->discardedTablespaces[strtolower($name)] = true;
        }
        if ($partitioning === null || count($selected) === count($partitioning->partitions)) {
            $table->data = new Heap(nextRow: $table->data->nextRow, autoIncrement: $table->data->autoIncrement);
        } else {
            $layout = new Partitions($table->definition, $partitioning, $context);
            $table->data->rows = array_filter($table->data->rows, static fn (array $row): bool => !in_array($layout->locate($row), $selected, true));
        }
        $table->indexStatistics = null;
        $table->statistics = [];
    }

    /**
     * Preserves legacy definition changes over detached files; modern releases refuse them.
     *
     * @throws SqlError When the release requires the existing file for an ALTER
     */
    public function alter(StoredTable $table, TableDefinition $next, bool $copies, bool $rebuild, Context $context): void
    {
        if ($table->discardedTablespaces === []) {
            return;
        }
        $definition = $table->definition;
        if ($copies || strcasecmp($definition->engine, $next->engine) !== 0) {
            $this->read($table, $context->modes->release);
        }
        if ($rebuild && !in_array($context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            throw new SqlError(SchemaError::TablespaceDiscarded, $this->message($definition->schema . '/' . $definition->name, $context->modes->release));
        }
        $context->diagnostics->warning(SchemaError::TablespaceDiscarded, 'InnoDB: ' . $this->message($definition->name, $context->modes->release));
    }

    /**
     * Refuses truncation without losing the detached state or its definition.
     * Legacy servers reopen the handler and leave it warm after the failed write.
     *
     * @throws SqlError When the file was discarded
     */
    public function truncate(StoredTable $table, Session $session): void
    {
        if (!$this->missing($table)) {
            return;
        }
        $definition = $table->definition;
        if ($session->settings()->legacy()) {
            $session->instance->dictionary->cache->open($definition->schema, $definition->name);
            $session->diagnostics->warning(SchemaError::TablespaceDiscarded, 'InnoDB: ' . $this->message($definition->name, $session->settings()->release()));
            $following = $session->settings()->release() === GrammarRelease::MySql5651
                ? [1146, QueryError::NoSuchTable->message($definition->schema, $definition->name)]
                : [1812, SchemaError::TablespaceUnavailable->message('`' . $definition->schema . '`.`' . $definition->name . '`')];
            throw new SqlError(SchemaError::TablespaceDiscarded, $this->message($definition->name, $session->settings()->release()), following: [$following]);
        }
        $session->instance->dictionary->cache->close($definition->schema, $definition->name);
        throw new SqlError(SchemaError::TablespaceDiscarded, $this->message($definition->schema . '/' . $definition->name, $session->settings()->release()), following: [[1146, QueryError::NoSuchTable->message($definition->schema, $definition->name)]]);
    }

    /**
     * Answers the release's wording for ER_TABLESPACE_DISCARDED.
     */
    public function message(string $name, GrammarRelease $release): string
    {
        return $release === GrammarRelease::MySql910 ? "Tablespace is discarded for table, '" . $name . "'" : SchemaError::TablespaceDiscarded->message($name);
    }
}
