<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Context;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;

/**
 * Determines the row-copying algorithm of an ALTER TABLE after its definition is resolved.
 *
 * Temporary tables always use COPY. Permanent MyISAM and MEMORY tables copy rows for
 * index storage changes, but keep renames and identical replacements in place.
 * Source: MySQL 8.4.7 sql/sql_table.cc is_inplace_alter_impossible(),
 * fill_alter_inplace_info(), and sql/handler.cc check_if_supported_inplace_alter().
 *
 * @visibility MySqlMemory
 */
final class AlterAlgorithm
{
    /**
     * Determines whether the change copies rows and checks its ALGORITHM and LOCK restrictions.
     * Column type or charset changes, ORDER BY, ALGORITHM=COPY, primary-key removal,
     * AUTO_INCREMENT addition and non-strict NOT NULL conversion can require copying.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-online-ddl-operations.html.
     *
     * @param list<int|null> $origins
     *
     * @throws \MySqlMemory\Error\SqlError When the algorithm or lock cannot be used
     */
    public function copies(TableDefinition $old, TableDefinition $new, array $origins, TableChange $change, Context $context): bool
    {
        [$retyped, $copies] = $this->columns($old, $new, $origins, $context);
        $copies = $copies || $change->copies || $change->algorithm === 'copy' || TableRebuild::copiesDefinition($old, $new, $change->layout);
        if (!$retyped) {
            (new Constraint\SpatialColumns())->alter($old, $new, $origins, $change->algorithm, $change->lock);
        }
        if ($old->temporary) {
            return $this->temporary($change);
        }
        $rekeyed = $this->changedKeys($old, $new, $origins, $change->layout);
        $innoDb = strcasecmp($old->engine, 'InnoDB') === 0;
        $engineCopy = !$innoDb && ($rekeyed || $retyped);
        $copies = $copies || $engineCopy;
        if ($innoDb && $change->algorithm !== 'copy') {
            if ($retyped) {
                $this->typeAlgorithm($change);
            } else {
                $copies = $this->virtualAlgorithm($old, $new, $origins, $change) || $copies;
            }
        }
        $this->instant($change, $copies, $rekeyed, $innoDb);
        if ($change->algorithm === 'copy' && $change->lock === 'none') {
            throw SchemaError::AlterOperationNotSupportedReason->error('LOCK=NONE', 'COPY algorithm requires a lock', 'LOCK=SHARED');
        }
        if ($engineCopy) {
            $this->engineAlgorithm($change);
        }

        return $copies || $retyped;
    }

    /**
     * Identifies column type changes and additional reasons to copy their stored rows.
     *
     * @param list<int|null> $origins
     * @return array{bool, bool} Whether types change, and whether other column changes require copying
     */
    public function columns(TableDefinition $old, TableDefinition $new, array $origins, Context $context): array
    {
        $retyped = false;
        $copies = false;
        foreach (array_filter($new->columns, static fn (ColumnDefinition $column): bool => $column->functional === null) as $position => $column) {
            $origin = $origins[$position] ?? null;
            if ($origin === null) {
                $copies = $copies || TableRebuild::copiesAdded($column);
                continue;
            }
            $before = $old->columns[$origin];
            $retyped = $retyped || !TableRebuild::columnInPlace($before, $column, in_array($context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true));
            $copies = $copies || TableRebuild::copiesColumn($before, $column, $context);
        }

        return [$retyped, $copies];
    }

    /**
     * Rejects INSTANT when storage work remains or InnoDB must rename an index.
     * More specific column and virtual-slot restrictions have already been checked.
     *
     * @throws \MySqlMemory\Error\SqlError When the requested change has no instant implementation
     */
    public function instant(TableChange $change, bool $copies, bool $rekeyed, bool $innoDb): void
    {
        if ($change->algorithm === 'instant' && ($copies || $rekeyed || ($innoDb && $this->renamedKeys($change->layout)))) {
            throw SchemaError::AlterOperationNotSupported->error('ALGORITHM=INSTANT', 'ALGORITHM=COPY/INPLACE');
        }
    }

    /**
     * Refuses algorithms and locks that cannot rebuild changed column types.
     *
     * @throws \MySqlMemory\Error\SqlError When the selected algorithm or lock requires an in-place type change
     */
    public function typeAlgorithm(TableChange $change): void
    {
        if ($change->algorithm === 'instant') {
            throw SchemaError::AlterOperationNotSupportedReason->error('ALGORITHM=INSTANT', 'Need to rebuild the table to change column type', 'ALGORITHM=COPY/INPLACE');
        }
        if ($change->algorithm === 'inplace') {
            throw SchemaError::AlterOperationNotSupportedReason->error('ALGORITHM=INPLACE', 'Cannot change column type INPLACE', 'ALGORITHM=COPY');
        }
        if ($change->lock === 'none') {
            throw SchemaError::AlterOperationNotSupportedReason->error('LOCK=NONE', 'Cannot change column type INPLACE', 'LOCK=SHARED');
        }
    }

    /**
     * Rejects an in-place algorithm before copying a temporary table; its LOCK is immaterial.
     *
     * @throws \MySqlMemory\Error\SqlError When a temporary table requests an in-place algorithm
     */
    public function temporary(TableChange $change): bool
    {
        if (in_array($change->algorithm, ['inplace', 'instant'], true)) {
            throw SchemaError::AlterOperationNotSupported->error('ALGORITHM=' . strtoupper($change->algorithm), 'ALGORITHM=COPY');
        }

        return true;
    }

    /**
     * Refuses an algorithm or lock that the engine cannot use for an index or type rebuild.
     *
     * @throws \MySqlMemory\Error\SqlError When the storage change requires copying rows
     */
    public function engineAlgorithm(TableChange $change): void
    {
        if ($change->algorithm === 'inplace') {
            throw SchemaError::AlterOperationNotSupported->error('ALGORITHM=INPLACE', 'ALGORITHM=COPY');
        }
        if ($change->lock === 'none') {
            throw SchemaError::AlterOperationNotSupported->error('LOCK=NONE', 'LOCK=SHARED');
        }
    }

    /**
     * Compares effective index definitions, following retained index and column identities.
     * A replaced index with the same name and storage is unchanged even though its origin is new.
     * Retained functional parts keep their old storage slots independently of SQL column positions.
     *
     * @param list<int|null> $origins
     */
    public function changedKeys(TableDefinition $old, TableDefinition $new, array $origins, TableLayout $layout): bool
    {
        if (count($old->keys) !== count($new->keys)) {
            return true;
        }
        $renames = [];
        $origins = $this->origins($new, $origins, $layout);
        foreach ($layout->keys as [$element, $origin]) {
            if ($element instanceof IndexDefinition && $origin !== null && $element->name !== null) {
                $renames[strtolower($origin->name)] = strtolower($element->name->column->value);
            }
        }
        $before = [];
        foreach ($old->keys as $key) {
            $before[$renames[strtolower($key->name)] ?? strtolower($key->name)] = $key;
        }
        foreach ($new->keys as $key) {
            $previous = $before[strtolower($key->name)] ?? null;
            if ($previous === null) {
                return true;
            }
            $positions = array_map(static fn (int $column): ?int => $origins[$column], $key->columns);
            if ($previous->columns !== $positions) {
                return true;
            }
            $mapped = new Key($key->name, $key->kind, $previous->columns, $key->prefixes, $key->descending, algorithm: $key->algorithm);
            if (!$previous->duplicates($mapped, $new->engine)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identifies metadata-only renames, which InnoDB does not support with ALGORITHM=INSTANT.
     */
    public function renamedKeys(TableLayout $layout): bool
    {
        foreach ($layout->keys as [$element, $origin]) {
            if ($element instanceof IndexDefinition && $origin !== null && $element->name !== null && $origin->name !== $element->name->column->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extends SQL column origins to the hidden slots of retained functional indexes.
     *
     * @param list<int|null> $origins Origins of visible SQL declarations
     * @return list<int|null> Origins of every stored slot, including functional key parts
     */
    public function origins(TableDefinition $new, array $origins, TableLayout $layout): array
    {
        $retained = [];
        foreach ($layout->keys as [$element, $origin]) {
            if ($element instanceof IndexDefinition && $origin !== null && $element->name !== null) {
                $retained[strtolower($element->name->column->value)] = $origin;
            }
        }
        $functional = [];
        foreach ($new->keys as $key) {
            $old = $retained[strtolower($key->name)] ?? null;
            foreach ($old === null ? [] : $key->columns as $part => $column) {
                if ($new->columns[$column]->functional !== null) {
                    $functional[$column] = $old->columns[$part] ?? null;
                }
            }
        }
        $mapped = [];
        foreach ($new->columns as $position => $column) {
            $mapped[] = $column->functional === null ? ($origins[$position] ?? null) : ($functional[$position] ?? null);
        }

        return $mapped;
    }

    /**
     * Applies InnoDB's restrictions on virtual-column order and newly indexed virtual slots.
     * Functional indexes use these same slots. Adding an indexed virtual column while
     * dropping another requires COPY; adding one alone needs a shared lock.
     * Source: MySQL 8.4.7 storage/innobase/handler/handler0alter.cc,
     * check_v_col_in_order() and ha_innobase::check_if_supported_inplace_alter().
     *
     * @param list<int|null> $origins Origins of visible SQL declarations
     * @throws \MySqlMemory\Error\SqlError When an algorithm or lock cannot perform the change
     */
    public function virtualAlgorithm(TableDefinition $old, TableDefinition $new, array $origins, TableChange $change): bool
    {
        $origins = $this->origins($new, $origins, $change->layout);
        $virtual = static fn (ColumnDefinition $column): bool => $column->generated !== null && !$column->stored;
        $dropped = array_diff(array_keys(array_filter($old->columns, $virtual)), $origins) !== [];
        $indexed = false;
        foreach ($new->keys as $key) {
            foreach ($key->columns as $column) {
                $indexed = $indexed || ($origins[$column] === null && $virtual($new->columns[$column]));
            }
        }
        $reordered = $this->virtualOrder($old, $new, $origins);
        if (!$reordered && !$indexed) {
            return false;
        }
        $copies = $reordered || $dropped;
        $reason = $copies ? 'INPLACE ADD or DROP of virtual columns cannot be combined with other ALTER TABLE actions' : 'ADD COLUMN col...VIRTUAL, ADD INDEX(col)';
        if ($change->algorithm === 'instant' || ($copies && $change->algorithm === 'inplace')) {
            throw SchemaError::AlterOperationNotSupportedReason->error('ALGORITHM=' . strtoupper($change->algorithm), $reason, $change->algorithm === 'instant' ? 'ALGORITHM=COPY/INPLACE' : 'ALGORITHM=COPY');
        }
        if ($change->lock === 'none') {
            throw SchemaError::AlterOperationNotSupportedReason->error('LOCK=NONE', $reason, 'LOCK=SHARED');
        }

        return $copies;
    }

    /**
     * Detects a newly added virtual slot before a retained virtual or auto-increment column,
     * or a different relative order of retained virtual slots.
     *
     * @param list<int|null> $origins Origins including hidden functional slots
     */
    public function virtualOrder(TableDefinition $old, TableDefinition $new, array $origins): bool
    {
        $added = false;
        $retained = [];
        foreach ($new->columns as $position => $column) {
            if ($column->generated === null || $column->stored) {
                if ($added && $column->autoIncrement) {
                    return true;
                }
                continue;
            }
            if ($origins[$position] === null) {
                $added = true;
            } elseif ($added) {
                return true;
            } else {
                $retained[] = $origins[$position];
            }
        }
        $before = array_keys(array_filter($old->columns, static fn (ColumnDefinition $column, int $position): bool => $column->generated !== null && !$column->stored && in_array($position, $origins, true), ARRAY_FILTER_USE_BOTH));

        return $before !== $retained;
    }
}
