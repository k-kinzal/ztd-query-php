<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

use Deriver\Value\Term;

/**
 * One guarded call target with an optional replacement receiver binding.
 * @visibility public
 * @example Naming an implementation
 *     (new \Deriver\Model\Provider\DispatchTarget('Service::run'))->symbol // => 'Service::run'
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
