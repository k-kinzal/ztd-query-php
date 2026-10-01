<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference;

/**
 * No visible declared column matches, and no missing declaration could supply it.
 * @visibility public
 * @example Distinguishing an invalid reference from an unknown type
 *     \SqlSemantics\Statement\Reference\MissingColumn::Value->name // => 'Value'
 */
enum MissingColumn
{
    case Value;
}
