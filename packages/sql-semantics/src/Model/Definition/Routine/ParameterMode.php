<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine;

/**
 * Classifies the argument direction, retaining whether the SQL specified a mode.
 * @visibility public
 * @example Inspecting an explicit input mode
 *     \SqlSemantics\Model\Definition\Routine\ParameterMode::Input->value // => 'IN'
 */
enum ParameterMode: string
{
    case Implicit = '';
    case Input = 'IN';
    case Output = 'OUT';
    case InputOutput = 'INOUT';
    case Variadic = 'VARIADIC';
}
