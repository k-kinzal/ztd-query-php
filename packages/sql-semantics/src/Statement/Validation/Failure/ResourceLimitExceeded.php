<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Failure;

use RuntimeException;

/**
 * Construction cannot finish within a safely detected operational resource limit.
 * @example Classifying this failure independently of SQL validity
 *     (new \SqlSemantics\Statement\Validation\Failure\ResourceLimitExceeded('The configured construction memory limit was reached.')) instanceof \RuntimeException // => true
 * @visibility public
 */
final class ResourceLimitExceeded extends RuntimeException
{
}
