<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The conflict target `ON CONSTRAINT constraint_name` that names the constraint ON CONFLICT arbitrates on.
 *
 * Mirrors the `conname` of PostgreSQL's `InferClause`.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html#SQL-ON-CONFLICT.
 *
 * @visibility public
 * @example Reading the constraint name
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('INSERT INTO t VALUES (1) ON CONFLICT ON CONSTRAINT t_pkey DO NOTHING');
 *     $insert->statement->conflict->target->constraint->value // => 't_pkey'
 */
final class ConstraintInference implements Node
{
    use Snapshot;

    /**
     * @param Name $constraint The constraint name
     */
    public function __construct(public readonly Name $constraint)
    {
    }

    /**
     * Writes ON CONSTRAINT and the name.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON', 'CONSTRAINT')->name($this->constraint, NameUse::Column);
    }
}
