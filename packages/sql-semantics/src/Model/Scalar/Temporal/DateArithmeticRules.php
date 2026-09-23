<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

/**
 * MySQL's release-dependent rules for time inputs and inferred parameter types.
 * @visibility public
 * @example Inspecting the rule family
 *     \SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules::Current->value // => 'mysql-8.0.28-and-later'
 */
enum DateArithmeticRules: string
{
    case Legacy = 'mysql-5.6-and-5.7';
    case Current = 'mysql-8.0.28-and-later';
}
