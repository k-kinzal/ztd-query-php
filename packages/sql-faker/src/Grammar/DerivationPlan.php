<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

final class DerivationPlan
{
    /** @var array<string, int> */
    private array $occurrences = [];

    /**
     * @param array<string, non-empty-list<ProductionPattern>> $patterns
     */
    public function __construct(
        private readonly array $patterns,
    ) {
    }

    public static function unrestricted(): self
    {
        return new self([]);
    }

    public function nextPattern(string $rule): ?ProductionPattern
    {
        $occurrence = $this->occurrences[$rule] ?? 0;
        $this->occurrences[$rule] = $occurrence + 1;

        return $this->patterns[$rule][$occurrence] ?? null;
    }

    public function restart(): self
    {
        return new self($this->patterns);
    }
}
