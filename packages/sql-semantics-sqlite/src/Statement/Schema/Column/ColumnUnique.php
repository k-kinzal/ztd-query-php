<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A UNIQUE column constraint.
 *
 * Rule: SQLITE-COLUMN-UNIQUE-001. No two rows may have the same non-NULL value
 * in the column; the conflict resolution says what a violating write does and
 * defaults to ABORT.
 * Source: https://sqlite.org/lang_createtable.html#unique_constraints.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a UNIQUE column constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a UNIQUE ON CONFLICT REPLACE)');
 *     $create->statement->columns[0]->constraints[0]->conflict?->value // => 'REPLACE'
 */
final class ColumnUnique implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param ConflictResolution|null $conflict The written ON CONFLICT resolution
     */
    public function __construct(public readonly ?ConflictResolution $conflict = null)
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
        $out->keyword('UNIQUE');
        if ($this->conflict !== null) {
            $out->keyword('ON', 'CONFLICT', $this->conflict->value);
        }
    }
}
