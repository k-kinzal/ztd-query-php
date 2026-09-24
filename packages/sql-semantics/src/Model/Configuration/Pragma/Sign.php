<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Pragma;

/**
 * Sign alternatives.
 *
 * @visibility public
 * @example Reading the sign of a numeric pragma argument
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build());
 *     $statement = $binder->bind('PRAGMA main.cache_size(-100)');
 *     $statement->value->sign // => \SqlSemantics\Model\Configuration\Pragma\Sign::Negative
 *     $binder->bind('PRAGMA cache_size=100')->value->sign // => \SqlSemantics\Model\Configuration\Pragma\Sign::Unsigned
 */
enum Sign: string
{
    case Unsigned = '';
    case Positive = '+';
    case Negative = '-';
}
