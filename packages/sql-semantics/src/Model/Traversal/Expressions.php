<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Traversal;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use WeakMap;

/**
 * Visits semantic expression identities once, including write and configuration effects.
 *
 * @visibility SqlSemantics
 */
final class Expressions
{
    /**
     * @return list<Expression>
     */
    public static function all(BoundStatement $statement): array
    {
        $seen = new WeakMap();
        $pending = [$statement];
        $result = [];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (is_object($value) && !$value instanceof Node && !$value instanceof Token && !isset($seen[$value])) {
                $seen[$value] = true;
                if ($value instanceof Expression) {
                    $result[] = $value;
                }
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
        return $result;
    }
}
