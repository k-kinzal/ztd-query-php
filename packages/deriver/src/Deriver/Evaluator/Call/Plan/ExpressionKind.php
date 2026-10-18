<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Plan;

/**
 * Pure expression forms supported by a semantic plan.
 * @visibility root
 */
enum ExpressionKind: string
{
    case Parameter = 'parameter';
    case State = 'state';
    case LocationRead = 'location-read';
    case Constant = 'constant';
    case Binary = 'binary';
    case Unary = 'unary';
    case Cast = 'cast';
    case Intrinsic = 'intrinsic';
    case External = 'external';
    case ArrayRead = 'array-read';
    case ArraySet = 'array-set';
}
