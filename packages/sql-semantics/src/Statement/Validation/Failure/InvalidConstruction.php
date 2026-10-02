<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Failure;

use RuntimeException;

/**
 * A supplied value is outside a constructor's documented input domain.
 * @example Classifying this failure independently of SQL validity
 *     (new \SqlSemantics\Statement\Validation\Failure\InvalidConstruction('A bound input belongs to another context.')) instanceof \RuntimeException // => true
 * @visibility public
 */
final class InvalidConstruction extends RuntimeException
{
}
