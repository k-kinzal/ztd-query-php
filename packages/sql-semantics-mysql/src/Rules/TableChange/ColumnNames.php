<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableChange;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownColumn;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Checks and writes the bare column name lists of partitioning clauses and table changes.
 *
 * Rule: MYSQL-COLUMN-NAMES-001. A column name is known to be absent only
 * when every relation visible at the position has a completely known row
 * shape and none of them has a slot of that name; names are compared with
 * the column name comparison of the context (MySQL column names are not
 * case sensitive). An absent name is the diagnostic UnknownColumn; nothing
 * is reported while a shape is open. Terminates: one pass over the names and
 * slots. Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-case-sensitivity.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnNames
{
    /**
     * Reports each name that no visible relation of the scope has.
     *
     * @param list<Name> $names The column names
     */
    public function check(array $names, Derivation $derivation, Environment $scope): void
    {
        foreach ($names as $name) {
            if ($this->present($name, $scope) === false) {
                $derivation->report(new UnknownColumn($name));
            }
        }
    }

    /**
     * Tells whether a visible relation of the scope has a column, or answers null when an open shape leaves it undecided.
     */
    public function present(Name $name, Environment $scope): ?bool
    {
        $decided = true;
        foreach ($scope->relations as $relation) {
            if ($this->count($relation->shape, $name, $scope) > 0) {
                return true;
            }
            $decided = $decided && $relation->shape->complete();
        }

        return $decided ? false : null;
    }

    /**
     * Counts the slots of a row shape a name denotes.
     */
    public function count(RowShape $shape, Name $name, Environment $scope): int
    {
        $count = 0;
        foreach ($shape->slots as $slot) {
            if ($slot->name !== null && $scope->context->columnNames->equal($slot->name->value, $name->value)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Writes a parenthesized, comma-separated list of column names.
     *
     * @param list<Name> $names The column names
     */
    public function write(Output $out, array $names): void
    {
        $out->symbol('(');
        foreach ($names as $index => $name) {
            if ($index > 0) {
                $out->symbol(',');
            }
            $out->name($name, NameUse::Column);
        }
        $out->symbol(')');
    }
}
