<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\State;

use Deriver\Deriver\Evaluator\Term;

/**
 * A namespaced abstract object slot with explicit initialization, clone, and invalidation contracts.
 * @visibility root
 * @example Resetting cached state on clone
 *     (new \Deriver\Deriver\Evaluator\Call\State\StateSlot('example.cache', 'array', \Deriver\Deriver\Evaluator\Term::array([]), \Deriver\Deriver\Evaluator\Call\State\ClonePolicy::Reset))->clone === \Deriver\Deriver\Evaluator\Call\State\ClonePolicy::Reset // => true
 */
final class StateSlot
{
    /**
     * @param string $id Domain-qualified slot identity, separate from PHP properties
     * @param string $type Finite PHP value type or union of types
     * @param Term|null $initial Initial value for newly allocated objects; null denotes a symbolic state input
     * @param ClonePolicy $clone copy or reset; copied reference cells retain their PHP aliasing
     * @param InvalidationPolicy $invalidation havoc or preserve when an unavailable call can reach the receiver
     */
    public function __construct(public readonly string $id, public readonly string $type = 'mixed', public readonly ?Term $initial = null, public readonly ClonePolicy $clone = ClonePolicy::Copy, public readonly InvalidationPolicy $invalidation = InvalidationPolicy::Havoc)
    {
    }
}
