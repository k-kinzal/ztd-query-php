<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A NOT NULL column constraint.
 *
 * Rule: SQLITE-COLUMN-NOT-NULL-001. The column cannot hold NULL; the conflict
 * resolution says what a violating write does and defaults to ABORT.
 * Source: https://sqlite.org/lang_createtable.html#not_null_constraints.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the conflict resolution of a NOT NULL constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a INT NOT NULL ON CONFLICT IGNORE)');
 *     $create->statement->columns[0]->constraints[0]->conflict // => \SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution::Ignore
 */
final class NotNull implements ColumnConstraint
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
        $out->keyword('NOT', 'NULL');
        if ($this->conflict !== null) {
            $out->keyword('ON', 'CONFLICT', $this->conflict->value);
        }
    }
}
