<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\View;

use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\Sqlite\Ir\From\SelectBounds;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks the additional placement and parameter rules of a view body.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ViewQueryBounds
{
    /**
     * Checks the body width, and every source and operand of the checked query.
     */
    public function check(Identifier $schema, GroupedSelect|Select|ValuesQuery $query, int $width): void
    {
        if ($query instanceof GroupedSelect) {
            $this->grouped($schema, $query, $width);

            return;
        }
        if ($query instanceof Select) {
            Check::input(count($query->columns) === $width, 'View columns must match the query width.');
            if ($query->source !== null) {
                $this->source($schema, $query->source);
            }
            foreach ($query->joins as $join) {
                $this->source($schema, $join->source);
            }
            (new SelectBounds())->check($query->columns, $query->source, $query->joins, $query->where, $query->orderBy, $query->limit, false);

            return;
        }

        Check::input(count($query->rows[0]->values) === $width, 'View columns must match the query width.');
        $pending = [];
        foreach ($query->rows as $row) {
            foreach ($row->values as $value) {
                $pending[] = [$value, 0, 0];
            }
        }
        (new ExpressionScope())->check([], $pending, false, false);
    }

    /**
     * Checks the additional scope and marker requirements placed on a grouped view body.
     */
    public function grouped(Identifier $schema, GroupedSelect $query, int $width): void
    {
        Check::input(count($query->columns) === $width, 'View columns must match the query width.');
        $widths = [];
        if ($query->source !== null) {
            $this->source($schema, $query->source);
            $widths[] = count($query->source->columns);
        }
        $pending = [];
        foreach ([$query->where, $query->having?->threshold] as $operand) {
            if ($operand !== null) {
                $pending[] = [$operand, 0, count($widths)];
            }
        }
        (new ExpressionScope())->check($widths, $pending, false, false);
    }

    /**
     * Checks the database identity of one occurrence in the view body.
     */
    public function source(Identifier $schema, TableSource|LocalSource $source): void
    {
        if ($source instanceof LocalSource) {
            return;
        }
        Check::input($schema->key() === 'temp' || $schema->key() === $source->schema->key(), 'A persistent view can only reference tables in its schema.');
    }
}
