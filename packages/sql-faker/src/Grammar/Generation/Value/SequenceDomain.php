<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

use Closure;
use Override;

/**
 * Declares a compound value from independent domains without materializing their Cartesian product.
 */
final class SequenceDomain implements ValueDomain
{
    /**
     * @var list<ValueDomain>
     */
    private readonly array $domains;

    /**
     * Retains declaration order, including an empty sequence.
     */
    public function __construct(ValueDomain ...$domains)
    {
        $this->domains = array_values($domains);
    }

    /**
     * @param Closure(positive-int): int $choose
     */
    #[Override]
    public function choose(Closure $choose): string
    {
        return implode('', array_map(static fn (ValueDomain $domain): string => $domain->choose($choose), $this->domains));
    }
    /**
     * @return list<int>
     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        $positions = [$offset];
        foreach ($this->domains as $domain) {
            $next = [];
            foreach ($positions as $position) {
                $next = [...$next, ...$domain->match($value, $position)];
            }
            $positions = array_values(array_unique($next));
        }
        return $positions;
    }

}
