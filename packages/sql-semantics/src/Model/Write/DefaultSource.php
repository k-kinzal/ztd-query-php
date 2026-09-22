<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

/**
 * Reads the destination column's declared default during a write operation.
 * This instruction is not a scalar expression and does not evaluate that default.
 * @visibility public
 * @example Choosing a column default
 *     \SqlSemantics\Model\Write\DefaultSource::Column->name // => 'Column'
 */
enum DefaultSource
{
    case Column;
}
