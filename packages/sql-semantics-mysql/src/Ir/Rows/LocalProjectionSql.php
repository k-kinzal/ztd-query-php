<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Ir\Ordering\LocalProjectionAliases;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithSql;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectSql;

/**
 * Emits the local scopes in closed definition order.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LocalProjectionSql
{
    /**
     * Supplies stable input aliases by ordinal at each independent scope boundary.
     */
    public function render(LocalProjection $projection, bool $localNames = false): string
    {
        $renderer = new SelectSql();
        $alias = '`__ztd_local`';
        $columns = [];
        foreach ($projection->columns as $position => $column) {
            $name = $localNames ? $renderer->localColumn($position) : $column->name->quoted();
            $columns[] = $alias . '.' . $renderer->localColumn($column->slot) . ' AS ' . $name;
        }
        $source = $projection->definition;
        $retain = (new LocalProjectionAliases())->requiresRequested($source);
        $input = match (true) {
            $source instanceof Select => $renderer->render($source, !$retain),
            $source instanceof WithProjection => (new WithSql())->render($source, !$retain),
            $source instanceof LocalProjection => $this->render($source, true),
        };
        $names = $retain ? $renderer->localColumnList($source instanceof WithProjection ? $source->width() : count($source->columns)) : '';

        return 'SELECT ' . implode(', ', $columns) . ' FROM (' . $input . ') AS ' . $alias . $names;
    }
}
