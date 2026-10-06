<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query\Facts;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * Derives the facts of a VALUES list.
 *
 * Rule: PG-VALUES-001. Every value is derived in the enclosing environment.
 * The columns are named `column1`, `column2`, and so on; the type of a column
 * is the common type of its values (PG-UNIFICATION-001), `text` when all of
 * them are untyped literals, and a column can be NULL when a value can. Rows
 * of different lengths and values that cannot be matched are reported. No
 * field is computed by a single expression. Terminates: one pass over the rows.
 * Source: https://www.postgresql.org/docs/17/sql-values.html,
 * https://www.postgresql.org/docs/17/typeconv-union-case.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ValuesFacts
{
    /**
     * Derives every value and answers the output columns.
     */
    public function derive(ValuesList $values, Derivation $derivation, Environment $outer): QueryFact
    {
        $width = count($values->rows[0]->values);
        $columns = [];
        $reported = false;
        foreach ($values->rows as $row) {
            if (count($row->values) !== $width && !$reported) {
                $derivation->report(new ArityMismatch(ArityRule::ValuesRows, 'VALUES', $width, count($row->values)));
                $reported = true;
            }
            foreach ($row->values as $position => $value) {
                $columns[$position][] = $derivation->scalar($value, $outer);
            }
        }
        $unification = new Unification();
        $items = [];
        for ($position = 0; $position < $width; $position++) {
            $facts = $columns[$position];
            $type = $unification->resolve($derivation->context, array_map(static fn ($fact) => $fact->type, $facts), 'VALUES');
            if ($type instanceof Invalid) {
                $derivation->report($type->cause);
            }
            $nullability = $unification->nullability(array_map(static fn ($fact) => $fact->nullability, $facts));
            $items[] = new Field($position, new OutputSlot(new Name('column' . ($position + 1)), $type, $nullability));
        }

        return new QueryFact($items, $derivation->context->columnNames);
    }
}
