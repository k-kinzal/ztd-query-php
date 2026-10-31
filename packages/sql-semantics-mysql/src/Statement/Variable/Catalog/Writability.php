<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Catalog;

/**
 * Which values of a system variable SET can change: every value, only the global one, or none.
 *
 * @visibility public
 * @example A variable whose session value is fixed
 *     \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability::GlobalOnly->name // => 'GlobalOnly'
 */
enum Writability
{
    case Writable;
    case GlobalOnly;
    case ReadOnly;
}
