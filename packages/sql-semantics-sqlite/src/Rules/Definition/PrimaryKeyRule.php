<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Identifier\Comparison;

/**
 * Finds the primary key of a table definition and whether it is the row identifier.
 *
 * Rule: SQLITE-PRIMARY-KEY-001. The first PRIMARY KEY constraint in written
 * order is the primary key; a further one is an error. A key of exactly one
 * column whose declared type is the standard name INTEGER is an integer
 * primary key, an alias of the rowid, with one exception that the manual
 * documents as a quirk kept for compatibility: the column constraint
 * `INTEGER PRIMARY KEY DESC` is not an alias, while the table constraint
 * `PRIMARY KEY (x DESC)` is. A term of a table constraint names a key column
 * when it is a plain name (SQLITE-KEY-TERM-001) of a declared column, compared
 * without regard to ASCII case. Terminates: one pass over the constraints.
 * Source: https://sqlite.org/lang_createtable.html#the_primary_key,
 * https://sqlite.org/lang_createtable.html#rowid. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class PrimaryKeyRule
{
    /**
     * Answers the key of a table definition.
     *
     * @param list<ColumnDomain> $domains The declared type of each column, in column order
     */
    public function key(CreateTable $definition, array $domains, Comparison $names): TableKey
    {
        $first = null;
        $constraints = 0;
        foreach ($definition->columns as $position => $column) {
            foreach ($column->constraints as $constraint) {
                if ($constraint instanceof ColumnPrimaryKey) {
                    $constraints++;
                    $first ??= new TableKey([$position], $domains[$position]->rowidCapable() && $constraint->direction !== SortDirection::Descending ? $position : null, 1, $constraint->autoincrement);
                }
            }
        }
        foreach ($definition->constraints as $run) {
            foreach ($run->items as $constraint) {
                if ($constraint instanceof TablePrimaryKey) {
                    $constraints++;
                    $first ??= $this->tableKey($definition, $constraint, $domains, $names);
                }
            }
        }

        return new TableKey($first->columns ?? [], $first?->rowid, $constraints, $first->autoincrement ?? false);
    }

    /**
     * Answers the key a PRIMARY KEY table constraint establishes.
     *
     * @param list<ColumnDomain> $domains The declared type of each column, in column order
     */
    public function tableKey(CreateTable $definition, TablePrimaryKey $constraint, array $domains, Comparison $names): TableKey
    {
        $columns = [];
        foreach ($constraint->terms as $term) {
            $name = (new KeyTerms())->column($term->expression);
            foreach ($definition->columns as $position => $column) {
                if ($name !== null && $names->equal($column->name->value, $name->value)) {
                    $columns[] = $position;
                    break;
                }
            }
        }
        $single = count($constraint->terms) === 1 && count($columns) === 1 && $domains[$columns[0]]->rowidCapable();

        return new TableKey($columns, $single ? $columns[0] : null, 1, $constraint->autoincrement);
    }
}
