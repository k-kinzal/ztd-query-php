<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Checks the column names a key, an index, a foreign key or a column list names against a row shape.
 *
 * Rule: PG-KEY-COLUMNS-001. A name is found among the slots of the shape by
 * exact comparison of the decoded names (identifiers are folded when they
 * are read). Only a complete shape can show that a column is absent; an open
 * shape reports nothing. Source:
 * https://www.postgresql.org/docs/17/sql-createtable.html. Termination: one
 * pass over the names and slots. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class KeyColumns
{
    /**
     * Answers the position of a named slot, or null when the shape has none of that name.
     */
    public function position(Derivation $derivation, RowShape $shape, Name $name): ?int
    {
        foreach ($shape->slots as $position => $slot) {
            if ($slot->name !== null && $derivation->context->columnNames->equal($slot->name->value, $name->value)) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Reports each name a complete shape lacks.
     *
     * @param list<Name> $names
     */
    public function report(Derivation $derivation, array $names, RowShape $shape, DefinitionRule $rule): void
    {
        if (!$shape->complete()) {
            return;
        }
        foreach ($names as $name) {
            if ($this->position($derivation, $shape, $name) === null) {
                $derivation->report(new DefinitionProblem($rule, $name));
            }
        }
    }
}
