<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaRelationOrder;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the resolved leaf slots against an earlier local relation's physical heading.
 *
 * @visibility SqlSemantics
 */
final class SchemaViewInputs
{
    /**
     * Checks the input's exact preceding role, column names and column kinds.
     * @param array<array-key, array{ColumnKind, TextCollation|array{string,string,string,string}|null}>|null $view
     */
    public static function check(TableSource $source, ?CreateTable $local, ?array $view): void
    {
        $fields = [];
        if ($source->kind === ExternalRelationKind::View) {
            Check::input($view !== null, 'A schema view must resolve its local view input to a preceding ordinary view.');
            foreach ($view as $name => [$kind]) {
                $fields[$name] = $kind;
            }
        } else {
            Check::input(
                $source->kind === ExternalRelationKind::BaseTable && $local !== null,
                'A schema view must resolve its local input to a preceding base table.'
            );
            foreach ($local->columns as $column) {
                $fields[$column->name->value] = SchemaRelationOrder::columnKind($column);
            }
        }
        foreach ($source->columns as $slot => $column) {
            Check::input(
                isset($fields[$column->value]) && $fields[$column->value] === $source->columnKind($slot),
                'A schema view requires each local input field in the checked native kind.'
            );
        }
    }
}
