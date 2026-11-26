<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate\Truth;

/**
 * The six native signed truth test operations.
 *
 * @visibility public
 * @example Selecting a complete comparison operation
 *     \SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthOperator::NotUnknown->value // => 'NOT UNKNOWN'
 */
enum TruthOperator: string
{
    case True = 'TRUE';
    case False = 'FALSE';
    case Unknown = 'UNKNOWN';
    case NotTrue = 'NOT TRUE';
    case NotFalse = 'NOT FALSE';
    case NotUnknown = 'NOT UNKNOWN';
}
