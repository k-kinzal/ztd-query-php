<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows\With;

use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjectionSql;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectSql;

/**
 * Renders lexical references with generated names tied to their coordinates.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class WithSql
{
    /**
     * Prints each declaration before its dependent uses.
     */
    public function render(WithProjection $query, bool $localNames = false): string
    {
        $definitions = [];
        foreach ($query->definitions as $position => $definition) {
            $definitions[] = $this->name($position) . ' AS (' . $this->query($definition->body, true) . ')';
        }

        return 'WITH ' . implode(', ', $definitions) . ' ' . $this->query($query->body, $localNames);
    }

    /**
     * Dispatches each closed member to its matching query renderer.
     */
    public function query(Select|LocalProjection|WithProjection|WithRead $query, bool $localNames): string
    {
        return match (true) {
            $query instanceof Select => (new SelectSql())->render($query, $localNames),
            $query instanceof LocalProjection => (new LocalProjectionSql())->render($query, $localNames),
            $query instanceof WithProjection => $this->render($query, $localNames),
            $query instanceof WithRead => $this->read($query, $localNames),
        };
    }

    /**
     * Renders the exact local relation and ordered field positions.
     */
    public function read(WithRead $query, bool $localNames): string
    {
        $source = $this->name($query->definition);
        $renderer = new SelectSql();
        $columns = [];
        foreach ($query->columns as $position => $column) {
            $label = $localNames ? $renderer->localColumn($position) : $column->name->quoted();
            $columns[] = $source . '.' . $renderer->localColumn($column->slot) . ' AS ' . $label;
        }

        return 'SELECT ' . implode(', ', $columns) . ' FROM ' . $source;
    }

    /**
     * Names one definition inside its owning scope.
     */
    public function name(int $position): string
    {
        return '`__ztd_cte_' . $position . '`';
    }
}
