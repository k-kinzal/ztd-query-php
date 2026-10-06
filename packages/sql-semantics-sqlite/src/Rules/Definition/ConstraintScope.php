<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

/**
 * What the expressions and column names inside a table definition can refer to.
 *
 * Rule: SQLITE-DEFINITION-SCOPE-001. A CHECK constraint, a key term and an
 * index expression see the columns of the table being defined or changed and
 * its row identifier. The expression of a generated column sees the columns
 * but "may not directly reference the ROWID". A DEFAULT value must be
 * constant and sees no column at all, so a column name in it is a missing
 * column. A column list of a constraint names declared columns only. The scope
 * is a working value of one derivation.
 * Source: https://sqlite.org/lang_createtable.html, https://sqlite.org/gencol.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ConstraintScope
{
    /**
     * @param Environment $row The position that sees the columns and the row identifier
     * @param Environment $columns The position that sees the columns only
     * @param Environment $constant The position that sees no column
     * @param list<Name>|null $names The declared column names, or null when the column list of the table is not completely known
     * @param Comparison $comparison How column names are compared
     */
    public function __construct(
        public readonly Environment $row,
        public readonly Environment $columns,
        public readonly Environment $constant,
        public readonly ?array $names,
        public readonly Comparison $comparison,
    ) {
    }

    /**
     * Tells whether a name is certainly not a declared column of the table.
     */
    public function lacks(Name $column): bool
    {
        if ($this->names === null) {
            return false;
        }
        foreach ($this->names as $name) {
            if ($this->comparison->equal($name->value, $column->value)) {
                return false;
            }
        }

        return true;
    }
}
