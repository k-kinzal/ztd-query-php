<?php

declare(strict_types=1);

namespace Deriver\Model\State;

use Deriver\Value\Term;

/**
 * A namespaced abstract object slot with explicit initialization, clone, and invalidation contracts.
 * @visibility public
 * @example Resetting cached state on clone
 *     (new \Deriver\Model\State\StateSlot('example.cache', 'array', \Deriver\Value\Term::array([]), 'reset'))->clone // => 'reset'
 */
final class StateSlot
{
    /**
     * @param string $id Domain-qualified slot identity, separate from PHP properties
     * @param string $type Finite PHP value type or union of types
     * @param Term|null $initial Initial value for newly allocated objects; null denotes a symbolic state input
     * @param string $clone copy or reset; copied reference cells retain their PHP aliasing
     * @param string $invalidation havoc or preserve when an unavailable call can reach the receiver
     */
    public function __construct(public readonly string $id, public readonly string $type = 'mixed', public readonly ?Term $initial = null, public readonly string $clone = 'copy', public readonly string $invalidation = 'havoc')
    {
    }
}
