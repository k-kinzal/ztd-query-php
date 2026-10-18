<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Provider;

use Deriver\Deriver\Evaluator\Term;

/**
 * One guarded call target with an optional replacement receiver binding.
 * @visibility root
 * @example Naming an implementation
 *     (new \Deriver\Deriver\Evaluator\Call\Provider\DispatchTarget('Service::run'))->symbol // => 'Service::run'
 */
final class DispatchTarget
{
    /**
     * @param string $symbol Fully qualified implementation
     * @param Term|null $receiver Receiver contract, or the original receiver
     * @param Term|null $condition Applicability predicate, or unconditional
     */
    public function __construct(public readonly string $symbol, public readonly ?Term $receiver = null, public readonly ?Term $condition = null)
    {
    }
}
