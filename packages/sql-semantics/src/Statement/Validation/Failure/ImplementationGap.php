<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Failure;

use RuntimeException;

/**
 * A required structuring, derivation, or rendering rule has not been implemented.
 *
 * This is a library defect and release blocker, never a semantic unknown or an
 * exemption from the selected grammar's coverage obligation.
 * @example Classifying this failure independently of SQL validity
 *     (new \SqlSemantics\Statement\Validation\Failure\ImplementationGap('The selected production has no semantic rule.')) instanceof \RuntimeException // => true
 * @visibility public
 */
final class ImplementationGap extends RuntimeException
{
}
