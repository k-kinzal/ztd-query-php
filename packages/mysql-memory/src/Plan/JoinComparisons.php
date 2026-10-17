<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Operator\Comparison\Compare;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Combine\NestedLoopJoin;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Retains the hash-key comparison of unindexed string joins in MySQL 8.0 and later.
 *
 * Rows still execute as nested loops. Hash equality matters independently of performance:
 * under legacy UCA, a space before a trailing ignorable character has a different hash key
 * but compares equal. Unicode 4.0 and 5.2 keys with maximum weight capacity above 1024 bytes use
 * this hash; shorter keys retain their padded weights. Indexed and constant lookups use the comparison directly.
 * Verified through SQL on MySQL 8.0.44 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class JoinComparisons
{
    /**
     * @param Planner $planner The statement's schema, binding facts and release
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Marks the string equalities of joins whose inputs are independently scanned.
     */
    public function plan(AccessPath $path, Select $select, Scope $scope): AccessPath
    {
        if (!$path instanceof NestedLoopJoin || $this->planner->settings->legacy() || $path->lateral) {
            return $path;
        }
        $dependencies = new ConstantTables($this->planner->compiler->facts);
        $constants = $dependencies->constants($select, $dependencies->tables($select->from, false));

        return new NestedLoopJoin($this->plan($path->left, $select, $scope), $this->plan($path->right, $select, $scope), $path->kind, $this->condition($path->condition, $scope, $constants), $path->lateral);
    }

    /**
     * Marks eligible conjuncts, preserving every other predicate and its short circuit order.
     *
     * @param array<int, true> $constants Constant relation occurrences
     */
    public function condition(?Evaluable $condition, Scope $scope, array $constants): ?Evaluable
    {
        if ($condition instanceof Retyped) {
            return new Retyped($this->condition($condition->evaluable, $scope, $constants) ?? $condition->evaluable, $condition->domain);
        }
        if ($condition instanceof Logic && $condition->operator === LogicalOperator::And) {
            if ($this->range($condition, $scope)) {
                return $condition;
            }
            return new Logic($condition->operator, $this->condition($condition->left, $scope, $constants) ?? $condition->left, $this->condition($condition->right, $scope, $constants) ?? $condition->right, $condition->domain);
        }
        if (!$condition instanceof Compare || $condition->comparator->mode !== Kind::String || !in_array($condition->operator, [ComparisonOperator::Equal, ComparisonOperator::NullSafeEqual], true)) {
            return $condition;
        }
        $capacity = max($condition->left->domain()->length, $condition->right->domain()->length) * $condition->comparator->collation->charset->maxLength * 8;
        if ((!str_ends_with($condition->comparator->collation->name, '_unicode_520_ci') && !str_ends_with($condition->comparator->collation->name, '_unicode_ci')) || $capacity <= 1024) {
            return $condition;
        }
        $left = $this->scanned($condition->left, $scope, $constants);
        $right = $this->scanned($condition->right, $scope, $constants);
        if ($left === null || $right === null || $left === $right) {
            return $condition;
        }

        return new Compare($condition->operator, $condition->left, $condition->right, $condition->comparator, $condition->domain, $condition->nullFromOperands, true);
    }

    /**
     * Detects indexed inequalities that select a range lookup instead of a hash join.
     */
    public function range(Evaluable $condition, Scope $scope): bool
    {
        if ($condition instanceof Retyped) {
            return $this->range($condition->evaluable, $scope);
        }
        if ($condition instanceof Logic && $condition->operator === LogicalOperator::And) {
            return $this->range($condition->left, $scope) || $this->range($condition->right, $scope);
        }
        if (!$condition instanceof Compare || !in_array($condition->operator, [ComparisonOperator::Less, ComparisonOperator::LessOrEqual, ComparisonOperator::Greater, ComparisonOperator::GreaterOrEqual], true)) {
            return false;
        }

        return ($this->scanned($condition->left, $scope, [], false) !== null && $this->scanned($condition->left, $scope, []) === null)
            || ($this->scanned($condition->right, $scope, [], false) !== null && $this->scanned($condition->right, $scope, []) === null);
    }

    /**
     * Answers the occurrence of a column without an indexed or constant lookup.
     *
     * @param array<int, true> $constants Constant relation occurrences
     * @param bool $indexes Whether an index excludes the column from a scan
     */
    public function scanned(Evaluable $value, Scope $scope, array $constants, bool $indexes = true): ?int
    {
        while ($value instanceof Retyped) {
            $value = $value->evaluable;
        }
        if (!$value instanceof ColumnRead || $value->depth !== 0) {
            return null;
        }
        foreach ($scope->tables as $id => $table) {
            $position = $value->position - $scope->offsets[$id];
            if ($position < 0 || $position >= count($table->columns)) {
                continue;
            }
            if (isset($constants[$id])) {
                return null;
            }
            foreach ($table->keys as $key) {
                if ($indexes && $key->visible && ($key->columns[0] ?? null) === $position) {
                    return null;
                }
            }

            return $id;
        }

        return null;
    }
}
