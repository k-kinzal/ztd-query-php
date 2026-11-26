<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesSql;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithSql;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;

/**
 * Emits the local scopes in closed definition order.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class LocalProjectionSql
{
    /**
     * Supplies stable input aliases by ordinal at each independent scope boundary.
     */
    public function render(LocalProjection $projection, bool $localNames = false): string
    {
        $renderer = new SelectSql();
        $alias = '"__ztd_local"';
        $columns = [];
        foreach ($projection->columns as $position => $column) {
            $name = $localNames ? $renderer->localColumn($position) : $column->name->quoted();
            $columns[] = $alias . '.' . $renderer->localColumn($column->slot) . ' AS ' . $name;
        }
        $source = $projection->definition;
        $input = match (true) {
            $source instanceof Select => $renderer->render($source, true),
            $source instanceof ValuesQuery => (new ValuesSql())->local($source),
            $source instanceof WithProjection => (new WithSql())->render($source, true),
            $source instanceof LocalProjection => $this->render($source, true),
        };

        return 'SELECT ' . implode(', ', $columns) . ' FROM (' . $input . ') AS ' . $alias;
    }
}
