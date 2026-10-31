<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Definition;

use MySqlMemory\Error\Family\SchemaError;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName;
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
    public function check(StatementSemantics $facts, ?Name $owner = null): void
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
    public function definition(IndexDefinition $index, StatementSemantics $facts, bool $legacy = false): void
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
    public function alter(\MySqlMemory\Command\Definition\TableLayout $layout, StatementSemantics $facts, GrammarRelease $release = GrammarRelease::MySql847, string $engine = 'InnoDB', string $collation = 'utf8mb4_general_ci'): void
    {
        $declared = new \SqlSemantics\Platform\MySql\Rules\Typing\Declared(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known($collation), $release);
        $columns = [];
        foreach ($layout->columns as [$column]) {
            $columns[mb_strtolower($column->name->column->value)] = \MySqlMemory\Typing\Domain::of($declared->column($column->specification), true);
        }
        $indexes = array_values(array_filter(array_map(static fn (array $key): ?IndexDefinition => $key[1] === null && $key[0] instanceof IndexDefinition ? $key[0] : null, $layout->keys)));
        foreach ($layout->options as $option) {
            if ($option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption) {
                $engine = $option->engine->value;
            }
        }
        $this->columns($indexes, $columns, $facts, $release, $engine);
    }

    /**
     * Checks creation-query keys against the columns supplied by their resolved query.
     *
     * @throws \MySqlMemory\Error\SqlError When a retained name or preceding key column is invalid
     */
    public function creation(\SqlSemantics\Platform\MySql\Statement\Statement $prepared, \MySqlMemory\Session\Session $session): void
    {
        \SqlSemantics\Diagnostic\Check::invariant($prepared instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable, 'Creation keys belong to the same table request.');
        if (array_filter($prepared->statementInformation()->diagnostics, static fn (\SqlSemantics\Statement\Fact\Diagnostic $problem): bool => $problem instanceof InvalidIndexName || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind) === []) {
            return;
        }
        if ($session->settings()->legacy()) {
            $this->check($prepared->statementInformation());
        }
        $indexes = array_values(array_filter($prepared->elements, static fn ($element): bool => $element instanceof IndexDefinition));
        $declared = new \MySqlMemory\Typing\Declared($session->settings()->connectionCollation, $session->settings()->release(), $session->resolution()->realAsFloat);
        $columns = [];
        foreach ($prepared->declarations()[0]->columns as $column) {
            $columns[mb_strtolower($column->name->value)] = $column->type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain ? \MySqlMemory\Typing\Domain::of($column->type, true) : $declared->domain($column->type);
        }
        $engine = (new \MySqlMemory\Command\Definition\StorageOptions())->engine($prepared, $session->variables);
        $this->columns($indexes, $columns, $prepared->statementInformation(), $session->settings()->release(), $engine);
    }

    /**
     * Checks ordered key declarations before length, prefix and storage validation.
     *
     * @param list<IndexDefinition> $indexes Added index declarations in definition order
     * @param array<string, \MySqlMemory\Typing\Domain> $columns Folded names and domains from the complete proposed declaration
     * @throws \MySqlMemory\Error\SqlError When the first name or missing key-column condition is reached
     */
    public function columns(array $indexes, array $columns, StatementSemantics $facts, GrammarRelease $release = GrammarRelease::MySql847, string $engine = 'InnoDB'): void
    {
        foreach ($indexes as $index) {
            $this->definition($index, $facts);
            (new IndexShapes())->definition($index, $facts, $release, $engine, $columns);
            $seen = [];
            foreach ($index->parts as $part) {
                if ($part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart && !isset($columns[mb_strtolower($part->column->value)])) {
                    throw SchemaError::KeyColumnMissing->error($part->column->value);
                }
                if ($part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart) {
                    $name = mb_strtolower($part->column->value);
                    if (isset($seen[$name])) {
                        throw SchemaError::DuplicateFieldName->error($part->column->value);
                    }
                    $seen[$name] = true;
                }
            }
        }
    }
    /**
     * Checks prepared key names unless a modern retained ALTER condition owns their order.
     *
     * @throws \MySqlMemory\Error\SqlError When a prepared index name is invalid
     */
    public function preparation(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable|\SqlSemantics\Platform\MySql\Statement\Table\CreateIndex|\SqlSemantics\Platform\MySql\Statement\Alter\DropIndex $statement, StatementSemantics $facts, bool $legacy): void
    {
        if ($legacy || !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable || !$statement->hasExecutionCondition()) {
            $this->check($facts);
        }
    }

}
