<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

/**
 * Closed CastMode alternatives.
 * @visibility public
 */
enum CastMode: string
{
    case Explicit = 'explicit';
    case Implicit = 'implicit';
}
