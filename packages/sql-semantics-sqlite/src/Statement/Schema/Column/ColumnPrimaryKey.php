<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A PRIMARY KEY column constraint.
 *
 * Rule: SQLITE-COLUMN-PRIMARY-KEY-001. The column alone is the primary key of
 * the table. What follows from that (whether the column is the row
 * identifier, whether it can hold NULL) depends on the declared type, the
 * sort order and the table options and is derived by the table definition:
 * a column declared `INTEGER PRIMARY KEY DESC` is not an alias of the rowid,
 * which is why the sort order is kept even though it does not order anything.
 * Source: https://sqlite.org/lang_createtable.html#the_primary_key,
 * https://sqlite.org/lang_createtable.html#rowid, https://sqlite.org/autoinc.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a primary key column constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY DESC ON CONFLICT FAIL AUTOINCREMENT)');
 *     $key = $create->statement->columns[0]->constraints[0];
 *     [$key->direction?->value, $key->conflict?->value, $key->autoincrement] // => ['DESC', 'FAIL', true]
 */
final class ColumnPrimaryKey implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param SortDirection|null $direction The written sort order
     * @param ConflictResolution|null $conflict The written ON CONFLICT resolution
     * @param bool $autoincrement Whether AUTOINCREMENT is written
     */
    public function __construct(public readonly ?SortDirection $direction = null, public readonly ?ConflictResolution $conflict = null, public readonly bool $autoincrement = false)
    {
    }

    /**
     * Derives nothing: the clause has no operand that depends on a declaration.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('PRIMARY', 'KEY');
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
        if ($this->conflict !== null) {
            $out->keyword('ON', 'CONFLICT', $this->conflict->value);
        }
        if ($this->autoincrement) {
            $out->keyword('AUTOINCREMENT');
        }
    }
}
