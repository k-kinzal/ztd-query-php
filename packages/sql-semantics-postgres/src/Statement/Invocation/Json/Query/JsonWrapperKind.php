<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

/**
 * Whether JSON_QUERY wraps the items found in an array.
 *
 * UNCONDITIONAL is the default after WITH and, like the optional ARRAY
 * word, noise: WITH WRAPPER is the unconditional wrapper.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING.
 *
 * @visibility public
 * @example Spelling the conditional wrapper
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapperKind::Conditional->value // => 'WITH CONDITIONAL'
 */
enum JsonWrapperKind: string
{
    case Without = 'WITHOUT';
    case Unconditional = 'WITH';
    case Conditional = 'WITH CONDITIONAL';

    /**
     * Tells whether items are wrapped, at least sometimes.
     */
    public function wraps(): bool
    {
        return $this !== self::Without;
    }
}
