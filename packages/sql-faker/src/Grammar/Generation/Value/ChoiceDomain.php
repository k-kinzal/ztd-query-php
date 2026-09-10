<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

use Closure;
use Override;

/**
 * Declares alternative constructive spelling domains, such as quoted and prefixed binary values.
 */
final class ChoiceDomain implements ValueDomain
{
    /**
     * @var non-empty-list<ValueDomain>
     */
    private readonly array $domains;

    /**
     * Requires at least one complete domain rather than a finite sample of an unknown set.
     */
    public function __construct(ValueDomain $first, ValueDomain ...$others)
    {
        $this->domains = [$first, ...array_values($others)];
    }

    /**
     * @param Closure(positive-int): int $choose
     */
    #[Override]
    public function choose(Closure $choose): string
    {
        return $this->domains[$choose(count($this->domains))]->choose($choose);
    }
    /**
     * @return list<int>
     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        $ends = [];
        foreach ($this->domains as $domain) {
            $ends = [...$ends, ...$domain->match($value, $offset)];
        }
        return array_values(array_unique($ends));
    }

}
