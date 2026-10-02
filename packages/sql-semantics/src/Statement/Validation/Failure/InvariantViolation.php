<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Failure;

use RuntimeException;

/**
 * An internal candidate or input/output correspondence violates a semantic rule.
 * @example Classifying this failure independently of SQL validity
 *     (new \SqlSemantics\Statement\Validation\Failure\InvariantViolation('The stored predicate differs from the input predicate.')) instanceof \RuntimeException // => true
 * @visibility public
 */
final class InvariantViolation extends RuntimeException
{
}
