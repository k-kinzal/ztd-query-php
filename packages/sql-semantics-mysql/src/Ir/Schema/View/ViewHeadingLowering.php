<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableDeclaration;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewColumns;
use SqlSemantics\Platform\MySql\Statement\View\ViewDefinition;

/**
 * Resolves the stored catalog heading before the view declaration is constructed.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewHeadingLowering
{
    /**
     * Resolves either an explicitly supplied heading or the native inherited names.
     * @return list<ViewColumn>
     */
    public function lower(BoundStatement $operation, ViewDefinition $definition, Select $query): array
    {
        $columns = [];
        if ($definition->columns !== null) {
            $names = new ViewCatalogHeading();
            foreach ($definition->columns as $column) {
                $columns[] = $names->explicit($column->value, $query->release);
            }

            return $columns;
        }

        $fact = $operation->facts->query($definition->query);
        $fields = (new TableDeclaration())->settled($fact);
        $assign = new ViewColumns($operation->context->profile);
        $generated = $assign->generated($definition->query, $fields);
        Check::input(count($generated) === count($query->columns), 'A view heading must resolve every result position.');
        $labels = array_map(static fn (ResultColumn $column): string => $column->name->value, $query->columns);
        foreach ($assign->allocate($labels, $generated) as $label) {
            Check::input($label !== null, 'A view heading must resolve every result name.');
            $columns[] = new ViewColumn(new Identifier($label));
        }

        return $columns;
    }
}
