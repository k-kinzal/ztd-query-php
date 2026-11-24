<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the placement of an ordinary persistent view definition.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ViewDefinitionBounds
{
    /**
     * Checks the resolved definition and returns its width.
     */
    public function width(Select|ValuesQuery $query, ViewName $name, ViewCheckOption $check, bool $ordinaryViewSource = false): int
    {
        if ($query instanceof ValuesQuery) {
            Check::input($check === ViewCheckOption::None, 'A view check option requires a simple base-table projection.');

            return count($query->rows[0]->cells);
        }
        Check::input(
            !$query->distinct && $query->orderBy === [] && $query->limit === null,
            'CREATE VIEW requires an unbounded ordinary projection.'
        );
        $source = $query->source;
        Check::input(
            $source === null || ($source instanceof TableSource && (
                $source->kind === ExternalRelationKind::BaseTable || ($ordinaryViewSource && $source->kind === ExternalRelationKind::View)
            )),
            'CREATE VIEW requires no source or one supported persistent relation.'
        );
        Check::input($check === ViewCheckOption::None || ($source instanceof TableSource && $source->kind === ExternalRelationKind::BaseTable), 'A view check option requires a simple base-table projection.');
        if ($source !== null) {
            Check::input(
                $source->schema->value !== 'pg_temp' && preg_match('/\Apg_(?:toast_)?temp_[0-9]+\z/D', $source->schema->value) === 0,
                'CREATE VIEW requires a persistent source schema.'
            );
            Check::input(
                $name->schema->value !== $source->schema->value || $name->view->value !== $source->table->value,
                'A persistent view definition cannot read its own identity.'
            );
        }
        foreach ($query->columns as $column) {
            $expression = $column->expression;
            while ($expression instanceof BuiltinCast) {
                $expression = $expression->operand;
            }
            Check::input(!$expression instanceof Parameter, 'A persistent view definition cannot bind a native parameter.');
        }

        return count($query->columns);
    }
}
