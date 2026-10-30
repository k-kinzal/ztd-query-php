<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Lookup;

use MySqlMemory\Dictionary\Partition\PartitionMethod;
use MySqlMemory\Evaluation\ExactDecimal;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\ConstantTables;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Storage\Partitions;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Scalar;

/**
 * Restricts scans whose partitioning inputs all have integer literal equalities.
 *
 * Partition selection belongs to the access path: a scan must not open a
 * discarded partition that its predicate excludes. Names are resolved through
 * column bindings, and partition expressions use the held table definition.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-pruning.html.
 *
 * @visibility MySqlMemory
 */
final class PartitionKeys
{
    /**
     * @param Planner $planner The statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Selects the partition of a fully constrained row before primary-key planning.
     */
    public function plan(Select $select, AccessPath $input, Scope $scope): AccessPath
    {
        if (!$input instanceof TableScan || !$select->from instanceof TableReference) {
            return $input;
        }
        $selected = $this->selected($select->where, $select->from, $input, $scope);
        if ($selected === $input->partitions) {
            return $input;
        }
        $scan = new TableScan($input->table, $selected, $input->handle);
        $scope->scans[spl_object_id($select->from)] = $scan;
        return $scan;
    }

    /**
     * Resolves the same partition restriction for a query or a single-table write.
     *
     * @return list<int>|null The reachable file positions, or every file
     * @throws \MySqlMemory\Error\SqlError When a partition expression fails
     */
    public function selected(?Operand $where, TableReference|\SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget $relation, TableScan $input, Scope $scope): ?array
    {
        $partitioning = $input->table->definition->partitioning;
        if ($partitioning === null || $partitioning->method === PartitionMethod::Key) {
            return $input->partitions;
        }
        $bindings = $this->bindings($where, $relation, $input, $scope);
        if ($bindings === null) {
            return $input->partitions;
        }
        $selected = [];
        foreach ($bindings as $values) {
            $row = array_values(array_replace(array_fill(0, $input->width(), null), $values));
            try {
                $selected[] = (new Partitions($input->table->definition, $partitioning, $this->planner->compiler->connection->context))->locate($row);
            } catch (\MySqlMemory\Error\SqlError $error) {
                if ($error->error !== \MySqlMemory\Error\Family\PartitionError::NoPartitionForValue) {
                    throw $error;
                }
            }
        }
        return $input->partitions === null ? $selected : array_values(array_intersect($input->partitions, $selected));
    }

    /**
     * Collects complete equality bindings; contradictory bindings select no row.
     *
     * @return list<array<int, int|float|string|null>>|null Candidate partition inputs, or no finite restriction
     */
    public function bindings(?Operand $where, TableReference|\SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget $relation, TableScan $input, Scope $scope): ?array
    {
        $partitioning = $input->table->definition->partitioning;
        if ($partitioning === null) {
            return null;
        }
        $values = [];
        $keys = new PrimaryKeys($this->planner);
        $frame = new Frame($this->planner->compiler->connection->context);
        foreach ((new ConstantTables($this->planner->compiler->facts))->conjuncts($where) as $condition) {
            if (!$condition instanceof Comparison || !in_array($condition->operator, [ComparisonOperator::Equal, ComparisonOperator::NullSafeEqual], true)) {
                continue;
            }
            foreach ([[$condition->left, $condition->right], [$condition->right, $condition->left]] as [$column, $value]) {
                $binding = $column instanceof Scalar && $value instanceof Scalar ? $keys->binding($column, $value, $relation, $input, $scope, false) : null;
                if ($binding === null) {
                    continue;
                }
                [$position, $constant, $comparison] = $binding;
                $number = $constant->evaluate($frame);
                if ($number instanceof ExactDecimal) {
                    continue;
                }
                if (array_key_exists($position, $values) && $comparison->compare($values[$position], $number, $frame->context) !== 0) {
                    return [];
                }
                $values[$position] = $number;
            }
        }
        return array_diff($partitioning->read, array_keys($values)) === [] ? [$values] : null;
    }
}
