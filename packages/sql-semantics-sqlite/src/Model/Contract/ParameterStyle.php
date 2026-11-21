<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Contract;

/**
 * Parameter markers interpreted by a fixed profile, without retaining parser objects.
 * @visibility public
 * @example Identifying the named parameter extension
 *     \SqlSemantics\Platform\Sqlite\Model\Contract\ParameterStyle::Named->value // => 'Named'
 */
enum ParameterStyle: string
{
    case Native = 'Native';
    case Named = 'Named';
}
