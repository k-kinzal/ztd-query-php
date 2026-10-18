<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Completion\Quality;

/**
 * Whether values retain exact expressions, abstractions, or opaque dependencies.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::ExactSymbolic->value // => 'exact-symbolic'
 * @visibility root
 */
enum ValuePrecision: string
{
    case ExactSymbolic = 'exact-symbolic';
    case Abstract = 'abstract';
    case Opaque = 'opaque';
}
