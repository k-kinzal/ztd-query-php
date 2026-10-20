<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Definition;

use MySqlMemory\Error\Family\SchemaError;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Reports resolved index-name conditions after target and functional-key preparation.
 *
 * Name validity is supplied by Semantics. The executor only selects the retained
 * occurrence whose declaration is being prepared.
 *
 * @visibility MySqlMemory
 */
final class IndexNames
{
    /**
     * Raises the first name condition, optionally restricted to one exact occurrence.
     *
     * @throws \MySqlMemory\Error\SqlError When Semantics diagnosed the selected index name
     */
    public function check(Facts $facts, ?Name $owner = null): void
    {
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof InvalidIndexName && ($owner === null || $problem->name === $owner)) {
                throw SchemaError::WrongIndexName->error($problem->name->value);
            }
        }
    }

    /**
     * Selects an explicitly named declaration before its ordinary key columns are built.
     *
     * @throws \MySqlMemory\Error\SqlError When the declaration's effective name is invalid
     */
    public function definition(IndexDefinition $index, Facts $facts, bool $legacy = false): void
    {
        if ($legacy) {
            $this->check($facts);
            return;
        }
        if ($index->kind === \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Primary) {
            return;
        }
        $name = $index->name->column ?? $index->constraint->name->column ?? null;
        if ($name !== null) {
            $this->check($facts, $name);
        }
    }

    /**
     * Orders modern added-key names and column availability after column actions.
     * The proposed layout has already applied drops and renames without publishing it.
     *
     * @throws \MySqlMemory\Error\SqlError When an added index name or key column is invalid
     */
    public function alter(\MySqlMemory\Command\Definition\TableLayout $layout, Facts $facts): void
    {
        $columns = array_map(static fn (array $column): string => mb_strtolower($column[0]->name->column->value), $layout->columns);
        $indexes = array_values(array_filter(array_map(static fn (array $key): ?IndexDefinition => $key[1] === null && $key[0] instanceof IndexDefinition ? $key[0] : null, $layout->keys)));
        $this->columns($indexes, $columns, $facts);
    }

    /**
     * Checks creation-query keys against the columns supplied by their resolved query.
     *
     * @throws \MySqlMemory\Error\SqlError When a retained name or preceding key column is invalid
     */
    public function creation(\MySqlMemory\Session\Resolution\TablePreparation $prepared, bool $legacy): void
    {
        if (array_filter($prepared->facts->diagnostics, static fn (\SqlSemantics\Statement\Fact\Diagnostic $problem): bool => $problem instanceof InvalidIndexName) === []) {
            return;
        }
        if ($legacy) {
            $this->check($prepared->facts);
            return;
        }
        $indexes = array_values(array_filter($prepared->statement->elements, static fn ($element): bool => $element instanceof IndexDefinition));
        $columns = array_map(static fn (\SqlSemantics\Statement\Declaration\Column $column): string => mb_strtolower($column->name->value), $prepared->table->columns);
        $this->columns($indexes, $columns, $prepared->facts);
    }

    /**
     * Checks ordered key declarations before length, prefix and storage validation.
     *
     * @param list<IndexDefinition> $indexes Added index declarations in definition order
     * @param list<string> $columns Folded names from the complete proposed declaration
     * @throws \MySqlMemory\Error\SqlError When the first name or missing key-column condition is reached
     */
    public function columns(array $indexes, array $columns, Facts $facts): void
    {
        foreach ($indexes as $index) {
            $this->definition($index, $facts);
            foreach ($index->parts as $part) {
                if ($part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart && !in_array(mb_strtolower($part->column->value), $columns, true)) {
                    throw SchemaError::KeyColumnMissing->error($part->column->value);
                }
            }
        }
    }
}
