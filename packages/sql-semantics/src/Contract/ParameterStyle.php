<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

/**
 * Parameter markers interpreted by a fixed profile, without retaining parser objects.
 * @visibility public
 * @example Identifying the named parameter extension
 *     \SqlSemantics\Contract\ParameterStyle::Named->value // => 'Named'
 */
enum ParameterStyle: string
{
    case Native = 'Native';
    case Named = 'Named';
}
