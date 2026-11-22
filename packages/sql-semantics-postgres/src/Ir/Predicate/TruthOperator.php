<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

/**
 * The supported Boolean truth tests.
 *
 * @visibility public
 * @example Writing the comparison against UNKNOWN
 *     \SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator::IsUnknown->value // => 'IS UNKNOWN'
 */
enum TruthOperator: string
{
    case IsTrue = 'IS TRUE';
    case IsNotTrue = 'IS NOT TRUE';
    case IsFalse = 'IS FALSE';
    case IsNotFalse = 'IS NOT FALSE';
    case IsUnknown = 'IS UNKNOWN';
    case IsNotUnknown = 'IS NOT UNKNOWN';
}
