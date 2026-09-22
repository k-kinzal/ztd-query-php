<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Pragma;

/**
 * Sign alternatives.
 *
 * @visibility public
 */
enum Sign: string
{
    case Unsigned = '';
    case Positive = '+';
    case Negative = '-';
}
