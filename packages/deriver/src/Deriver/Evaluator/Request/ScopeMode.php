<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Request;

/**
 * Whether analysis starts from symbolic inputs or declared entrypoints.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Evaluator\Request\ScopeMode::Symbolic->value // => 'symbolic'
 * @visibility root
 */
enum ScopeMode: string
{
    case Symbolic = 'symbolic';
    case Entrypoint = 'entrypoint';
}
