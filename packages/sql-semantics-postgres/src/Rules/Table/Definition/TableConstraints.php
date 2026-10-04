<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\Exclusion;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\ForeignKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableCheck;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableUnique;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnOptions;

/**
 * Tells which values are table constraints, and checks the element lists of typed tables and partitions.
 *
 * Rule: PG-TABLE-CONSTRAINTS-001. A table constraint is one of the
 * `ConstraintElem` forms: CHECK, UNIQUE, PRIMARY KEY (with columns or USING
 * INDEX), EXCLUDE and FOREIGN KEY. The elements of a typed table or a
 * partition are column options and table constraints. Source:
 * https://www.postgresql.org/docs/17/sql-createtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TableConstraints
{
    /**
     * The table constraint classes.
     */
    private const CLASSES = [TableCheck::class, TableUnique::class, TablePrimaryKey::class, IndexConstraint::class, Exclusion::class, ForeignKey::class];

    /**
     * Tells whether a value is a table constraint.
     */
    public function admits(object $value): bool
    {
        return in_array($value::class, self::CLASSES, true);
    }

    /**
     * Checks the elements of a typed table or a partition.
     *
     * @param array<array-key, object|scalar|null> $elements
     * @return list<Clause>
     */
    public function typedElements(array $elements): array
    {
        $checked = [];
        foreach (Check::listOf($elements, Clause::class, 'Table elements are an ordered list of clauses.') as $element) {
            Check::input($element instanceof ColumnOptions || $this->admits($element), 'An element of a typed table or a partition is column options or a table constraint.');
            $checked[] = $element;
        }

        return $checked;
    }
}
