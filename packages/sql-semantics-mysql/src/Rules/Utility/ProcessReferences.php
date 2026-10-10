<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill;
use SqlSemantics\Statement\Node;

/**
 * Refuses table and stored-function dependencies in a process identifier.
 *
 * Rule: MYSQL-KILL-DEPENDENCIES-001. KILL accepts scalar expressions and table-free subqueries,
 * but refuses a table reference or a stored function before opening tables or resolving names.
 * A missing stored function is refused in the same way. Verified against MySQL 9.1.0.
 * Terminates: every finite expression node is visited once.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/kill.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProcessReferences
{
    /**
     * Reports the first table or stored function in the expression, including its subqueries.
     */
    public function check(Node $expression, Derivation $derivation): void
    {
        $pending = [$expression];
        $native = new NativeFunctions();
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node instanceof TableReference || $node instanceof ExplicitTable || ($node instanceof FunctionCall && ($node->schema !== null || !$native->exists($derivation->context->profile->grammar, $node->name->value)))) {
                $derivation->report(new NotSupportedYet(Kill::DEPENDENCIES));

                return;
            }
            foreach (get_object_vars($node) as $value) {
                foreach (is_array($value) ? $value : [$value] as $child) {
                    if ($child instanceof Node) {
                        $pending[] = $child;
                    }
                }
            }
        }
    }
}
