<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Server;

/**
 * Result kind a loadable function declares; the library computes the value.
 * @visibility public
 * @example Naming the decimal result
 *     \SqlSemantics\Model\Definition\Server\LoadableResult::Decimal->value // => 'DECIMAL'
 */
enum LoadableResult: string
{
    case String = 'STRING';
    case Real = 'REAL';
    case Decimal = 'DECIMAL';
    case Integer = 'INTEGER';
}
