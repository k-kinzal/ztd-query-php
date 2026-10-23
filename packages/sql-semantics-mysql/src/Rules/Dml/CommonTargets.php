<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Identifies a target necessarily bound to a local common-table declaration.
 *
 * Exact spelling is independent of instance case settings. A case-distinct or
 * database-qualified reference can still name a physical table on a supported
 * instance, so those references remain binding constraints.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CommonTargets
{
    /**
     * Tells whether every name-comparison setting binds this reference to a CTE.
     */
    public function contains(?WithClause $with, QualifiedName $name): bool
    {
        if (!$with instanceof With || $name->schema !== null) {
            return false;
        }
        foreach ($with->tables as $table) {
            if ($table->name->value === $name->name->value) {
                return true;
            }
        }
        return false;
    }
}
