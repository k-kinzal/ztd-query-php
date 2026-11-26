<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Binding;

/**
 * The integral conversion of one projected native parameter.
 *
 * @visibility public
 * @example Selecting an unsigned input interpretation
 *     \SqlSemantics\Platform\MySql\Ir\Binding\ParameterTarget::Unsigned->value // => 'UNSIGNED'
 */
enum ParameterTarget: string
{
    case Signed = 'SIGNED';
    case Unsigned = 'UNSIGNED';
}
