<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Micro;

use Closure;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\SourceRef;

/**
 * A dispatchable operation, independent of the graph node that requested it.
 * @visibility root
 */
final class Site
{
    /**
     * @param Closure(int|string): Term $input Lazy value dependencies
     * @param Closure(int|string): Term $truth Lazy boolean dependencies
     */
    public function __construct(public readonly string $operation, public readonly string $name, public readonly SourceRef $source, public readonly Closure $input, public readonly Closure $truth)
    {
    }
}
