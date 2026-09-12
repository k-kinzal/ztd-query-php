<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation\Completion;

use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Symbol;

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
     * @return list<Production>
     */
    public function matching(string $name, ?ProductionPattern $pattern): array
    {
        $key = $name . ':' . serialize($pattern);
        if (!isset($this->cache[$key])) {
            $matches = [];
            foreach ($this->grammar->ruleMap[$name]->alternatives ?? [] as $ordinal => $production) {
                if ($pattern === null || $pattern->matches(array_map(static fn (Symbol $symbol): string => $symbol->value(), $production->symbols), $ordinal)) {
                    $matches[] = $production;
                }
            }
            $this->cache[$key] = $matches;
        }
        return $this->cache[$key];
    }
}
