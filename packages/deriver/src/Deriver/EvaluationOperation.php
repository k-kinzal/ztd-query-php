<?php

declare(strict_types=1);

namespace Deriver\Deriver;

/**
 * Operations for which an application can supply a micro evaluator.
 * @visibility public
 * @example Selecting function evaluation
 *     \Deriver\Deriver\EvaluationOperation::Function->value // => 'function'
 */
enum EvaluationOperation: string
{
    case Function = 'function';
    case Constant = 'constant';
    case Binary = 'binary';
    case Unary = 'unary';
    case Cast = 'cast';
    case Conditional = 'conditional';
    case Branch = 'branch';
    case Intrinsic = 'intrinsic';
}
