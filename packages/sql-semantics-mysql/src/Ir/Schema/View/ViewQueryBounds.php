<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\LocalProjectionAliases;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Closes the checked view body over its admitted persistent source and field roles.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewQueryBounds
{
    /**
     * Checks structural conditions owned by the DDL boundary.
     */
    public function check(Select $query, ViewName $target, ViewAlgorithm $algorithm): void
    {
        (new LocalProjectionAliases())->check($query);
        Check::input(!$query->distinct && $query->orderBy === [] && $query->limit === null, 'A checked view requires an ordinary unbounded projection.');
        $source = $query->source;
        Check::input(
            $source === null || ($source instanceof TableSource && $source->kind === ExternalRelationKind::BaseTable),
            'A checked view reads one persistent base table or an independent literal projection.'
        );
        Check::input($source !== null || $algorithm !== ViewAlgorithm::Merge, 'A MERGE view requires a mergeable base-table projection.');
        if ($source !== null) {
            Check::input(
                $source->schema->value !== $target->schema->value || $source->table->value !== $target->view->value,
                'A view cannot read its own target identity.'
            );
        }
        foreach ($query->columns as $column) {
            $value = $column->expression;
            Check::input(
                $value instanceof ColumnReference || $value instanceof TextColumnReference
                || $value instanceof IntegerLiteral || $value instanceof TextLiteral || $value instanceof NullLiteral || $value instanceof BooleanLiteral,
                'A checked view result must read a native field or a literal without parameters.'
            );
        }
    }
}
