<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\With;

use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjectionSql;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesSql;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;

/**
 * Renders lexical references with generated names tied to their coordinates.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
            $policy = $definition->materialization->value;
            $definitions[] = $this->name($position) . ' AS ' . ($policy === '' ? '' : $policy . ' ')
                . '(' . $this->query($definition->body, true) . ')';
        }

        return 'WITH ' . implode(', ', $definitions) . ' ' . $this->query($query->body, $localNames);
    }

    /**
     * Dispatches each closed member to its matching query renderer.
     */
    public function query(Select|LocalProjection|ValuesQuery|WithProjection|WithRead $query, bool $localNames): string
    {
        return match (true) {
            $query instanceof Select => (new SelectSql())->render($query, $localNames),
            $query instanceof LocalProjection => (new LocalProjectionSql())->render($query, $localNames),
            $query instanceof ValuesQuery => $localNames ? (new ValuesSql())->local($query) : $query->toString(),
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
        return '"__ztd_cte_' . $position . '"';
    }
}
