<?php

declare(strict_types=1);

namespace SqlSemantics\Diagnostic;

use LogicException;

/**
 * A candidate model that failed a correspondence or integrity check before publication.
 *
 * It is a defect of the library. The candidate is discarded; it is never
 * returned as an operation or downgraded to an unknown value.
 *
 * @visibility public
 * @example Raising the failure of an internal check
 *     \SqlSemantics\Diagnostic\Check::invariant(false, 'The rendered SQL lost a predicate.') // throws \SqlSemantics\Diagnostic\InvariantViolation
 */
final class InvariantViolation extends LogicException
{
}
