<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A bare NULL column constraint: the statement that the column may hold NULL.
 *
 * Rule: SQLITE-COLUMN-NULL-001. The clause is accepted for compatibility and
 * changes nothing, with or without a conflict resolution; it does not undo a
 * NOT NULL constraint of the same column.
 * Source: https://sqlite.org/lang_createtable.html (grammar `ccons ::= NULL onconf`).
 * Status: Implemented.
 *
 * @visibility public
 * @example Keeping a NULL constraint without effect on the column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a INT NULL)');
 *     $create->declarations()[0]->columns[0]->nullability // => \SqlSemantics\Statement\Type\Nullability::Nullable
 */
final class NullAllowed implements ColumnConstraint
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
        $out->keyword('NULL');
        if ($this->conflict !== null) {
            $out->keyword('ON', 'CONFLICT', $this->conflict->value);
        }
    }
}
