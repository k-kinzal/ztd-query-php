<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Catalog;

/**
 * The values a system variable takes: ON and OFF, integers, unsigned integers, numbers, or text.
 *
 * @visibility public
 * @example Naming the shape of a switch
 *     \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\ValueShape::Boolean->name // => 'Boolean'
 */
enum ValueShape
{
    case Boolean;
    case Integer;
    case Unsigned;
    case Double;
    case Text;
}
