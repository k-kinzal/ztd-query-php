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
        if ($change->algorithm === 'instant' && ($retyped || $copies || $rekeyed || ($innoDb && $this->renamedKeys($change->layout)))) {
            throw SchemaError::AlterOperationNotSupported->error('ALGORITHM=INSTANT', 'ALGORITHM=COPY/INPLACE');
        }
        if ($change->algorithm === 'copy' && $change->lock === 'none') {
            throw SchemaError::AlterOperationNotSupportedReason->error('LOCK=NONE', 'COPY algorithm requires a lock', 'LOCK=SHARED');
        }
        if ($engineCopy) {
            $this->engineAlgorithm($change);
        } elseif ($retyped) {
            $this->typeAlgorithm($change);
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
     * Refuses algorithms and locks that cannot rebuild changed column types.
     *
     * @throws \MySqlMemory\Error\SqlError When the selected algorithm or lock requires an in-place type change
     */
    public function typeAlgorithm(TableChange $change): void
    {
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
        $retained = [];
        foreach ($layout->keys as [$element, $origin]) {
            if ($element instanceof IndexDefinition && $origin !== null && $element->name !== null) {
                $renames[strtolower($origin->name)] = strtolower($element->name->column->value);
                $retained[strtolower($element->name->column->value)] = true;
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
            $positions = array_map(static fn (int $column, int $part): ?int => $new->columns[$column]->functional !== null && isset($retained[strtolower($key->name)]) ? ($previous->columns[$part] ?? null) : ($origins[$column] ?? null), $key->columns, array_keys($key->columns));
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
}
