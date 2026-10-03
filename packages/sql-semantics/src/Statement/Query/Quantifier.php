<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Query;

/**
 * Duplicate retention or elimination, including an explicit ALL request.
 * @visibility public
 * @example Requesting duplicate elimination
 *     \SqlSemantics\Statement\Query\Quantifier::Distinct->value // => 'DISTINCT'
 */
enum Quantifier: string
{
    case Default = '';
    case All = 'ALL';
    case Distinct = 'DISTINCT';
}
