<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Prepared;

use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Finds the expressions that supply contexts for prepared markers.
 *
 * Traversal reads only semantic children. Input position comes from the
 * resolved ParameterBinding, independently of this traversal's order.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ParameterContexts
{
    /**
     * @return list<Scalar> Expressions in the statement, including nested queries
     */
    public function expressions(Node $root): array
    {
        $expressions = [];
        $pending = [$root];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (is_array($node)) {
                array_push($pending, ...array_values($node));
            } elseif ($node instanceof Node) {
                if ($node instanceof Scalar) {
                    $expressions[] = $node;
                }
                array_push($pending, ...array_values(get_object_vars($node)));
            }
        }

        return $expressions;
    }
}
