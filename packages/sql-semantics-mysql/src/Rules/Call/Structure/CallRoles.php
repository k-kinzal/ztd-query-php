<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call\Structure;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall;
use SqlSemantics\Statement\Node;

/**
 * Keeps intrinsic call-identity and signature conditions outside contextualized roles.
 *
 * Unused CTE definitions and ignored VALUES-write clauses retain their operands.
 * Stored branches still contextualize their calls at declaration. These are
 * structural dependencies, independent of catalogs and execution eligibility.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CallRoles
{
    /**
     * Answers the first call whose retained template cannot be contextualized.
     */
    public function problem(Node $root): \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem|\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount|\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument|\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction|null
    {
        foreach ((new ReadDependencies())->nodes($root) as $node) {
            if ($node instanceof DormantCall) {
                return $node->problem();
            }
            if ($node instanceof DeferredCall) {
                $problem = ProgramNames::problem($node->name);
                if ($problem !== null) {
                    return $problem;
                }
            }
        }
        return null;
    }
}
