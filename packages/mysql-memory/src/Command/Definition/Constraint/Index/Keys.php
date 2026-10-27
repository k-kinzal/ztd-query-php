<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint\Index;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

/**
 * Builds storage keys from resolved semantic index declarations and column slots.
 *
 * @visibility MySqlMemory
 */
final class Keys
{
    /**
     * @param Planner $planner The declaration's resolved facts, execution context and language
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Resolves the ordered columns, prefixes and index options against the declared storage.
     *
     * @param list<ColumnDefinition> $columns The columns already prepared for the declaration
     * @throws \MySqlMemory\Error\SqlError When a name, column or index capability is invalid
     */
    public function build(IndexDefinition $index, array $columns, string $engine = 'InnoDB'): Key
    {
        (new \MySqlMemory\Session\Problem\Definition\IndexNames())->definition($index, $this->planner->compiler->facts, $this->planner->settings->legacy());
        (new \MySqlMemory\Command\Definition\Constraint\IndexOptions())->warn($index, $engine, $this->planner->compiler->connection->context);
        $positions = [];
        $prefixes = [];
        $descending = [];
        foreach ($index->parts as $part) {
            \SqlSemantics\Diagnostic\Check::invariant($part instanceof ColumnPart || $part instanceof ExpressionPart || $part instanceof ArrayPart, 'An index part names a column or supplies its expression.');
            $name = $part instanceof ColumnPart ? $part->column->value : (new \MySqlMemory\Command\Definition\Constraint\FunctionalKeys())->name($part);
            $position = null;
            foreach ($columns as $candidate => $column) {
                if (strcasecmp($column->name, $name) === 0) {
                    $position = $candidate;
                }
            }
            if ($position === null) {
                throw SchemaError::KeyColumnMissing->error($name);
            }
            $positions[] = $position;
            $prefixes[] = $part instanceof ColumnPart ? (new \MySqlMemory\Command\Definition\Constraint\ColumnPrefixes())->length($part, $columns[$position]->domain, $index->kind) : null;
            $descending[] = $this->descending($part->direction, $engine);
        }
        $kind = match ($index->kind) {
            IndexKind::Primary => KeyKind::Primary,
            IndexKind::Unique => KeyKind::Unique,
            IndexKind::FullText => KeyKind::FullText,
            IndexKind::Spatial => KeyKind::Spatial,
            IndexKind::Index => KeyKind::Index,
        };

        $key = new Key($kind === KeyKind::Primary ? 'PRIMARY' : ($index->name?->column->value ?? $index->constraint?->name?->column->value ?? ''), $kind, $positions, $prefixes, $descending, visible: (new \MySqlMemory\Command\Definition\Constraint\IndexOptions())->visible($index), algorithm: (new \MySqlMemory\Command\Definition\Constraint\IndexOptions())->algorithm($index, $engine, $this->planner->settings->legacy()));

        return (new \MySqlMemory\Command\Definition\Constraint\SpatialKeys($this->planner))->resolve($index, $key, $columns, $engine);
    }

    /**
     * MySQL 5.6 and 5.7 discard index ordering; later releases require engine support.
     * Source: MySQL 8.4.7 sql/sql_table.cc count_keys().
     *
     * @throws \MySqlMemory\Error\SqlError When an engine cannot store a descending index
     */
    public function descending(?Direction $direction, string $engine): bool
    {
        $descending = !$this->planner->settings->legacy() && $direction === Direction::Descending;
        if ($descending && strcasecmp($engine, 'InnoDB') !== 0) {
            throw SchemaError::EngineUnsupportedOperation->error('descending indexes');
        }

        return $descending;
    }

}
