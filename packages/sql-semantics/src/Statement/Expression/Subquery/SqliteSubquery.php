<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Subquery;

use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Query\ScopedRows;
use SqlSemantics\Statement\Query\ScopedSelect;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A query evaluated at one expression site, retaining its lexical parent namespace.
 * @visibility public
 * @example Keeping a query distinct from a scalar or existence test
 *     is_a(\SqlSemantics\Statement\Expression\Subquery\SqliteSubquery::class, \SqlSemantics\Statement\Expression\ScalarExpression::class, true) // => false
 */
final class SqliteSubquery
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The nested query must have been resolved at this exact expression site.
     */
    public function __construct(public readonly Scope $scope, public readonly ScopedSelect|ScopedRows $query)
    {
        $parent = $query->scope->parent;
        \SqlSemantics\Statement\Validation\Check::input(($parent instanceof SqliteAliasScope ? $parent->scope : $parent) === $scope, 'A subquery must retain its immediate lexical input scope.');
    }

    /**
     * Reports the output width without discarding malformed VALUES row widths.
     */
    public function width(): int
    {
        return $this->query instanceof ScopedSelect ? count($this->query->fields()->items) : count($this->query->rows[0]->expressions);
    }

    /**
     * Returns output domains from the first result row or SELECT projection.
     * @return list<ScalarExpression>
     */
    public function outputs(): array
    {
        return $this->query instanceof ScopedSelect
            ? array_map(static fn (\SqlSemantics\Statement\Projection\Field $field): ScalarExpression => $field->expression, $this->query->fields()->items)
            : $this->query->rows[0]->expressions;
    }

    /**
     * Includes predicates and restrictions even when an existence test ignores projected values.
     * @return list<ScalarExpression>
     */
    public function operands(): array
    {
        if ($this->query instanceof ScopedRows) {
            return array_merge(...array_map(static fn (\SqlSemantics\Statement\Query\Row $row): array => $row->expressions, $this->query->rows));
        }
        $operands = $this->outputs();
        foreach ([$this->query->where, $this->query->limit?->count, $this->query->limit?->offset] as $expression) {
            if ($expression !== null) {
                $operands[] = $expression;
            }
        }
        return $operands;
    }

    /**
     * Distinguishes semantic contradictions from missing declaration information.
     */
    public function invalid(): ?Invalid
    {
        if ($this->query instanceof ScopedRows && count(array_unique($this->query->widths())) !== 1) {
            return Invalid::InconsistentRowWidth;
        }
        foreach ($this->operands() as $operand) {
            $type = $operand->type();
            if ($type instanceof Invalid) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Retains lookup sites at every nested query depth.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return array_merge(...array_map(static fn (ScalarExpression $expression): array => $expression->references(), $this->operands()));
    }
}
