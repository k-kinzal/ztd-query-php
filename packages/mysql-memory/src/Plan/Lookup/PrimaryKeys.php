<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Lookup;

use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\ConstantTables;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Source\KeyLookup;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Gives a single-table integer primary-key equality its own access path.
 *
 * Initially only literal integer bindings in consistent reads are used. Partial keys, other
 * coercions, joins, locking reads and index hints retain their existing paths. Locking reads
 * must also inspect keys another transaction changed in an uncommitted row version.
 * Column identity and key positions come from the bound schema, never from SQL text matching.
 *
 * @visibility MySqlMemory
 */
final class PrimaryKeys
{
    /**
     * @param Planner $planner The statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Plans one lookup when every integer primary-key column has an equality binding.
     *
     * @param bool $runtime Whether preparation may read statement-constant key values
     */
    public function plan(Select $select, AccessPath $input, Scope $scope, bool $runtime = false): AccessPath
    {
        $locking = new \MySqlMemory\Plan\Locking($this->planner);
        $transaction = $locking->transaction();
        $mode = $locking->writes() ? $transaction?->access->sourceReads() : $transaction?->access->plainReads();
        if (!$input instanceof TableScan || !$select->from instanceof TableReference || $select->from->indexHints !== [] || $select->locking !== [] || $mode !== null) {
            return $input;
        }
        $bindings = [];
        foreach ((new ConstantTables($this->planner->compiler->facts))->conjuncts($select->where) as $condition) {
            if (!$condition instanceof Comparison || !in_array($condition->operator, [ComparisonOperator::Equal, ComparisonOperator::NullSafeEqual], true)) {
                continue;
            }
            foreach ([[$condition->left, $condition->right], [$condition->right, $condition->left]] as [$column, $value]) {
                if (!$column instanceof Scalar || !$value instanceof Scalar) {
                    continue;
                }
                $binding = $this->binding($column, $value, $select->from, $input, $scope, $runtime);
                if ($binding !== null) {
                    $bindings[$binding[0]] ??= $binding;
                }
            }
        }
        foreach ($input->table->definition->keys as $key) {
            if ($key->kind === KeyKind::Primary && $key->columns !== [] && array_diff($key->columns, array_keys($bindings)) === []) {
                return new KeyLookup($input, array_map(static fn (int $position): array => $bindings[$position], $key->columns));
            }
        }

        return $input;
    }

    /**
     * Resolves an integer key value bound to a column of this table occurrence.
     *
     * @param bool $runtime Whether a preparation check accepts statement constants
     *
     * @return array{int, Evaluable, Comparator}|null
     */
    public function binding(Scalar $column, Scalar $value, TableReference|\SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget $table, TableScan $input, Scope $scope, bool $runtime = false): ?array
    {
        $compiler = $this->planner->compiler;
        $facts = new ConstantTables($compiler->facts);
        $resolved = $facts->column($column);
        $literal = $facts->unwrap($value);
        if ($resolved === null || $resolved->relation !== $table || (!$literal instanceof NumberLiteral && (!$runtime || !$compiler->constancy($literal)->constant() || (new \MySqlMemory\Evaluation\Compile\Walker())->find($literal, \SqlSemantics\Statement\Query::class) !== []))) {
            return null;
        }
        $name = $resolved->declaration()?->name->value;
        $position = $name === null ? null : $input->table->definition->position($name);
        if ($position === null) {
            return null;
        }
        $domain = $input->table->definition->columns[$position]->domain;
        $constant = $compiler->compile($literal, $scope);
        if ($domain->kind !== Kind::Integer || (!in_array($constant->domain()->kind, $runtime ? [Kind::Integer, Kind::Null] : [Kind::Integer], true))) {
            return null;
        }

        return [$position, $constant, Comparator::of($domain, $constant->domain(), '=', $compiler->settings->connectionCollation, $compiler->settings->release())];
    }

    /**
     * Keeps per-read calls such as RAND out of a legacy constant-table preparation check.
     * Variable assignments still participate; subqueries retain their own execution boundary.
     */
    public function preparable(Scalar $condition): bool
    {
        foreach ((new \MySqlMemory\Evaluation\Compile\Walker())->find($condition, \SqlSemantics\Statement\Node::class, false) as $node) {
            if ($node instanceof \SqlSemantics\Statement\Query || (($node instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall || $node instanceof \SqlSemantics\Platform\MySql\Statement\Call\ClockCall) && \MySqlMemory\Evaluation\Compile\Constancy::uncacheable($node, release: $this->planner->settings->release()))) {
                return false;
            }
        }

        return true;
    }
}
