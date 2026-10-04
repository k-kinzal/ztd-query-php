<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Renames the columns of a FROM item by the column aliases written after its correlation name.
 *
 * Rule: PG-COLUMN-ALIAS-001. The aliases rename the first columns in order;
 * the remaining columns keep their names. More aliases than columns are
 * reported when every column is known; when the columns are not all known,
 * an alias past the known columns names a column whose type depends on the
 * missing inputs. A renamed column refers to the column it renames.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-TABLE-ALIASES.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ColumnAliases
{
    /**
     * Answers the shape renamed by the aliases.
     *
     * @param list<Name> $aliases
     */
    public function apply(RowShape $shape, ?Name $alias, array $aliases, Derivation $derivation): RowShape
    {
        if ($aliases === []) {
            return $shape;
        }
        if ($shape->complete() && count($aliases) > count($shape->slots)) {
            $derivation->report(new ArityMismatch(ArityRule::ColumnAliases, $alias->value ?? '', count($shape->slots), count($aliases)));
        }
        $slots = [];
        foreach ($shape->slots as $position => $slot) {
            $name = $aliases[$position] ?? null;
            $slots[] = $name === null ? $slot : new OutputSlot($name, $slot->type, $slot->nullability, null, $slot);
        }
        for ($position = count($shape->slots); !$shape->complete() && $position < count($aliases); $position++) {
            $slots[] = new OutputSlot($aliases[$position], new Dependent($shape->missing), Nullability::Dependent);
        }

        return new RowShape($slots, $shape->missing);
    }

    /**
     * Answers the shape a query output has as a FROM item: one slot per field, referring to it.
     */
    public function fromQuery(QueryFact $fact): RowShape
    {
        $slots = [];
        foreach ($fact->projection as $item) {
            if ($item instanceof Field) {
                $slots[] = new OutputSlot($item->name, $item->type, $item->nullability, null, $item->slot);
            }
        }

        return new RowShape($slots, $fact->shape->missing);
    }
}
