<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the row shape of a derived table or a common table from its query and its column list.
 *
 * Rule: MYSQL-DERIVED-SHAPES-001. Without a column list the columns are the
 * output slots of the query, with their names (MYSQL-SELECT-ITEM-NAME-001);
 * a slot whose name depends on missing inputs keeps them as its unnamed
 * inputs, so that a column lookup in the relation depends on them
 * (CORE-COLUMN-LOOKUP-001). With a column list the list names the
 * columns in order and the query gives their types, which depend on the
 * missing inputs of the query while its columns are not all known; a list whose length
 * differs from a complete query is reported, and a column without a slot is
 * invalid. Duplicate column names are reported. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/derived-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DerivedShapes
{
    /**
     * Answers the shape of the rows the query produces under the given column names.
     *
     * @param list<Name> $columns The column list; empty when the query names the columns
     */
    public function shape(QueryFact $fact, array $columns, Derivation $derivation): RowShape
    {
        $shape = $fact->shape;
        if ($columns === []) {
            $slots = [];
            foreach ($shape->slots as $slot) {
                $slots[] = new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot, $slot->unnamed);
            }
            $this->unique($slots, $derivation);

            return new RowShape($slots, $shape->missing);
        }
        $problem = null;
        if ($shape->complete() && count($shape->slots) !== count($columns)) {
            $problem = new CountMismatch(CountedList::DerivedColumns, count($columns), count($shape->slots));
            $derivation->report($problem);
        }
        $slots = [];
        foreach ($columns as $position => $column) {
            $slot = $shape->complete() ? $shape->slots[$position] ?? null : null;
            if ($slot !== null) {
                $slots[] = new OutputSlot($column, $slot->type, $slot->nullability, null, $slot);
            } elseif ($problem !== null) {
                $slots[] = new OutputSlot($column, new Invalid($problem), Nullability::Dependent);
            } else {
                $slots[] = new OutputSlot($column, new Dependent($shape->missing), Nullability::Dependent);
            }
        }
        $this->unique($slots, $derivation);

        return new RowShape($slots);
    }

    /**
     * Reports a column name that occurs twice.
     *
     * @param list<OutputSlot> $slots
     */
    public function unique(array $slots, Derivation $derivation): void
    {
        $seen = [];
        foreach ($slots as $slot) {
            if ($slot->name === null) {
                continue;
            }
            $key = $derivation->context->columnNames->fold($slot->name->value);
            if (isset($seen[$key])) {
                $derivation->report(new Misuse(MisuseRule::DuplicateColumn));

                return;
            }
            $seen[$key] = true;
        }
    }
}
