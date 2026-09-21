<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Derivation\Completion;

use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Symbol;

/**
 * Reuses immutable pattern/production matches across all frontiers and occurrences of the same constrained search.
 */
final class PatternProductions
{
    /**
     * @var array<string, list<Production>>
     */
    private array $cache = [];

    /**
     * A fixed grammar makes pattern matches independent of search budget and occurrence counters.
     */
    public function __construct(private readonly Grammar $grammar)
    {
    }

    /**
     * Keeps legacy occurrence counters shared across specialized copies of a rule.
     */
    public function sourceRule(string $name): string
    {
        return $this->grammar->sourceRule($name);
    }

    /**
     * @return list<Production>
     */
    public function matching(string $name, ?ProductionPattern $pattern): array
    {
        $key = $name . ':' . serialize($pattern);
        if (!isset($this->cache[$key])) {
            $matches = [];
            foreach ($this->grammar->ruleMap[$name]->alternatives ?? [] as $ordinal => $production) {
                if ($pattern === null || $pattern->matches(array_map(fn (Symbol $symbol): string => $this->grammar->sourceRule($symbol->value()), $production->symbols), $this->grammar->sourceOrdinal($name, $ordinal))) {
                    $matches[] = $production;
                }
            }
            $this->cache[$key] = $matches;
        }
        return $this->cache[$key];
    }
}
