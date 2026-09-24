<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

/**
 * Closed CastMode alternatives.
 * @visibility public
 * @example Reading the conversion mode
 *     \SqlSemantics\Model\Scalar\Operator\CastMode::Explicit->value // => 'explicit'
 */
enum CastMode: string
{
    case Explicit = 'explicit';
    case Implicit = 'implicit';
}
