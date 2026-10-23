<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query as M;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Follows resolved projection sources without recovering names or query source text.
 * Set operands retain every contributing expression; stored and recursive sources
 * remain unknown. This is a structural dependency query, not evaluation.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProjectionSources
{
    /**
     * @param Facts $facts The bound facts of the statement
     */
    public function __construct(public readonly Facts $facts)
    {
    }

    /**
     * Tells whether every source is independent of input rows.
     */
    public function constant(Query $query): bool
    {
        $expressions = $this->expressions($query, 0, true);
        $keys = new \SqlSemantics\Platform\MySql\Rules\Query\Grouping\GroupKeys();
        return $expressions !== [] && !in_array(false, array_map(fn (?Scalar $expression): bool => $expression !== null && $keys->constant($expression, $this->facts), $expressions), true);
    }

    /**
     * Follows a selected position through every set branch and, optionally, bound columns.
     *
     * @param list<int> $visited Query identities on the current path, preventing recursive CTE traversal
     * @return list<Scalar|null> NULL marks a stored or recursively dependent source
     */
    public function expressions(Query|M\Set\LeadingUnion $query, int $position, bool $columns, array $visited = []): array
    {
        if (in_array(spl_object_id($query), $visited, true)) {
            return [null];
        }
        $visited[] = spl_object_id($query);
        if ($query instanceof M\ParenthesizedQuery || $query instanceof M\QueryStatement) {
            return $this->expressions($query->query, $position, $columns, $visited);
        }
        if ($query instanceof M\QueryExpression) {
            return $this->expressions($query->body, $position, $columns, $visited);
        }
        if ($query instanceof M\Set\SetOperation || $query instanceof M\Set\OrderedSetOperation || $query instanceof M\Set\LeadingUnion) {
            return [...$this->expressions($query->left, $position, $columns, $visited), ...$this->expressions($query->right, $position, $columns, $visited)];
        }
        if ($query instanceof M\ValuesQuery) {
            return array_map(static fn (M\ValueRow $row): ?Scalar => ($row->values[$position] ?? null) instanceof Scalar ? $row->values[$position] : null, $query->rows);
        }
        $field = $this->facts->query($query)->projection[$position] ?? null;
        return $field instanceof Field ? $this->field($field, $columns, $visited) : [null];
    }

    /**
     * Reads the expression or resolved column behind a projected field.
     *
     * @param list<int> $visited Query identities on the current path
     * @return list<Scalar|null> Projected sources
     */
    public function field(Field $field, bool $columns, array $visited): array
    {
        $expression = $field->expression;
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if (!$columns || ($expression !== null && !$expression instanceof ColumnUse)) {
            return [$expression];
        }
        $resolution = $expression instanceof ColumnUse ? $this->facts->scalar($expression)->resolution : $field->resolution;
        if ($resolution instanceof AliasTarget) {
            return $this->field($resolution->field, true, $visited);
        }
        return $resolution instanceof ResolvedColumn ? $this->column($resolution, $visited) : [null];
    }

    /**
     * Follows a derived or common-table output by its resolved slot identity.
     *
     * @param list<int> $visited Query identities on the current path
     * @return list<Scalar|null> Bound output sources
     */
    public function column(ResolvedColumn $column, array $visited): array
    {
        $relation = $column->relation;
        $fact = $this->facts->relation($relation);
        $common = $fact->table;
        $query = match (true) {
            $relation instanceof DerivedTable => $relation->query,
            $common instanceof CommonTable && $common->definition instanceof M\With\CommonTableExpression => $common->definition->query,
            default => null,
        };
        if ($query === null) {
            return [null];
        }
        for ($slot = $column->slot; $slot instanceof OutputSlot; $slot = $slot->origin) {
            $position = array_search($slot, $fact->shape->slots, true);
            if (is_int($position)) {
                return $this->expressions($query, $position, true, $visited);
            }
        }
        return [null];
    }
}
