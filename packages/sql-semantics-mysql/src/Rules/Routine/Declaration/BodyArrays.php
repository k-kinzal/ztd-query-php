<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Statement\Node;

/**
 * Excludes multi-valued index casts from stored program instructions.
 *
 * This restriction includes local defaults, subqueries, unreachable branches
 * and index definitions in the body. Strings and external code are not SQL
 * instructions. Observed through SQL on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyArrays
{
    /**
     * Answers the intrinsic conflict without consulting execution state.
     */
    public function problem(Node $body): ?ProgramProblem
    {
        $pending = [$body];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ((($node instanceof Cast || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast) && $node->array) || $node instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart) {
                return new ProgramProblem(ProgramRule::ArrayCast);
            }
            if (is_array($node)) {
                array_push($pending, ...array_values($node));
            } elseif ($node instanceof Node) {
                array_push($pending, ...array_values(get_object_vars($node)));
            }
        }
        return null;
    }
}
