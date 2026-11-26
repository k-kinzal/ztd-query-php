<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\View;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Rows\Compound;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundBounds;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks every simple arm and the outer clauses of a compound view definition.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CompoundViewBounds
{
    /**
     * Admits complete compound views with no declared-type affinity in the result.
     */
    public function check(Identifier $schema, Compound $query, int $width, int $prefixBytes): void
    {
        $arms = [$query->first];
        foreach ($query->steps as $step) {
            $arms[] = $step->query;
        }
        $bounds = new ViewQueryBounds();
        foreach ($arms as $arm) {
            $bounds->check($schema, $arm, $width);
            $this->types($arm);
        }
        if ($query->limit !== null) {
            $items = [[$query->limit->count, 0, 0]];
            if ($query->limit->offset !== null) {
                $items[] = [$query->limit->offset, 0, 0];
            }
            (new ExpressionScope())->check([], $items, false, false);
        }
        (new CompoundBounds())->check($query->first, $query->steps, $query->orderBy, $query->limit, $prefixBytes);
    }

    /**
     * Checks the affinity of each exported arm operand.
     */
    public function types(Select|ValuesQuery $arm): void
    {
        $values = [];
        if ($arm instanceof Select) {
            foreach ($arm->columns as $field) {
                $values[] = $field->expression;
            }
        } else {
            foreach ($arm->rows as $row) {
                foreach ($row->values as $value) {
                    $values[] = $value;
                }
            }
        }

        foreach ($values as $value) {
            while ($value instanceof CollatedExpression) {
                $value = $value->operand;
            }
            Check::input(!$value instanceof ColumnReference && !$value instanceof CastExpression, 'Compound view results require no declared-type affinity.');
        }
    }
}
