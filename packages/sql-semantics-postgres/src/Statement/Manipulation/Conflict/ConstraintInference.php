<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The conflict target `ON CONSTRAINT constraint_name` that names the constraint ON CONFLICT arbitrates on.
 *
 * Mirrors the `conname` of PostgreSQL's `InferClause`.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html#SQL-ON-CONFLICT.
 *
 * @visibility SqlSemantics
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
