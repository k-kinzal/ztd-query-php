<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithRead;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Checks a child whose consumer replaces its result aliases with local names.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LocalProjectionAliases
{
    /**
     * Checks that selecting local aliases preserves the child's native diagnostics.
     */
    public function check(Select|LocalProjection|WithProjection|WithRead $child): void
    {
        Check::input(!$this->requiresRequested($child), 'A locally remapped output cannot discard requested alias diagnostics.');
    }

    /**
     * Identifies a result whose native alias must survive local positional naming.
     */
    public function requiresRequested(Select|LocalProjection|WithProjection|WithRead $child): bool
    {
        $output = $child instanceof WithProjection ? $child->body : $child;
        if (!$output instanceof Select) {
            return false;
        }
        foreach ($output->columns as $column) {
            if ($column->name instanceof RequestedResultAlias) {
                return true;
            }
        }

        return false;
    }
}
