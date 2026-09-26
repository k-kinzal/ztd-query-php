<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

/**
 * Separates additional candidates from an explicit exhaustive-world contract.
 * @visibility public
 * @example Partial dispatch remains open
 *     (new \Deriver\Model\Provider\DispatchDecision())->exhaustive // => false
 */
final class DispatchDecision
{
    /**
     * @param list<DispatchTarget> $targets Guarded implementation candidates
     * @param bool $exhaustive Whether these candidates exhaust this provider's stated world
     * @param list<string> $assumptions Explicit world assumptions
     */
    public function __construct(public readonly array $targets = [], public readonly bool $exhaustive = false, public readonly array $assumptions = [])
    {
    }
}
