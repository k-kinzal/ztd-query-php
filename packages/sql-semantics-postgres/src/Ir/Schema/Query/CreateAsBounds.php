<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Query;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the supported read sources and binding restrictions of an ordinary table definition.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CreateAsBounds
{
    /**
     * Validates the independent query placement and returns its native heading width.
     */
    public function width(Select|ValuesQuery $query): int
    {
        if ($query instanceof ValuesQuery) {
            return count($query->rows[0]->cells);
        }
        $source = $query->source;
        Check::input(
            $source === null || ($source instanceof TableSource && $source->kind === ExternalRelationKind::BaseTable),
            'CREATE TABLE AS requires no source or one persistent base table.'
        );
        if ($source !== null) {
            Check::input(
                $source->schema->value !== 'pg_temp' && preg_match('/\Apg_(?:toast_)?temp_[0-9]+\z/D', $source->schema->value) === 0,
                'CREATE TABLE AS requires a persistent source schema.',
            );
        }
        $expressions = [];
        foreach ($query->columns as $column) {
            $expressions[] = $column->expression;
        }
        foreach ($query->orderBy as $order) {
            $expressions[] = $order->target;
        }
        foreach ($expressions as $expression) {
            while ($expression instanceof BuiltinCast) {
                $expression = $expression->operand;
            }
            Check::input(!$expression instanceof Parameter, 'CREATE TABLE AS requires a parameter-free defining query.');
        }

        return count($query->columns);
    }
}
