<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

/**
 * Stores one finite minimum-cost derivation and its actual production choices.
 */
final class WitnessNode
{
    /**
     * @var array<string, true>
     */
    public readonly array $contains;

    /**
     * @param list<self> $children
     */
    public function __construct(
        public readonly string $rule,
        public readonly int $ordinal,
        public readonly array $children,
        public readonly int $cost,
        public readonly int $state
    ) {
        $contains = [$rule . "\0" . $ordinal => true];
        foreach ($children as $child) {
            $contains += $child->contains;
        }
        $this->contains = $contains;
    }

    /**
     * Reconstructs leftmost expansion order, including every sibling.
     *
     * @return list<array{string, int}>
     */
    public function sequence(): array
    {
        $sequence = [[$this->rule, $this->ordinal]];
        foreach ($this->children as $child) {
            array_push($sequence, ...$child->sequence());
        }
        return $sequence;
    }
}
