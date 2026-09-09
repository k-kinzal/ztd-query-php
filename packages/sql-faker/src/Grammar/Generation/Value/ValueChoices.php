<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

use Closure;
use InvalidArgumentException;

/**
 * Per-plan decisions, memoized so repeated candidate inspection cannot consume more randomness.
 * The caller interprets bytes; domains only receive bounded index decisions.
 */
final class ValueChoices
{
    /**
     * @var array<string, string|null>
     */
    private array $values = [];

    /**
     * @param Closure(positive-int): ?int $choice Null selects representatives after decision input ends
     */
    public function __construct(private readonly Closure $choice)
    {
    }

    /**
     * Samples once per occurrence and domain; null retains the declared representatives.
     */
    public function value(int $index, string $definition, ?ValueDomain $domain): ?string
    {
        if ($domain === null) {
            return null;
        }
        $key = $index . ':' . $definition;
        if (!array_key_exists($key, $this->values)) {
            $this->values[$key] = $this->index(2) === 0 ? null : $domain->choose($this->index(...));
        }
        return $this->values[$key];
    }

    /**
     * @param positive-int $count
     * @throws InvalidArgumentException When a caller supplies an out-of-range decision
     */
    public function index(int $count): int
    {
        $index = ($this->choice)($count) ?? 0;
        if ($index < 0 || $index >= $count) {
            throw new InvalidArgumentException('Value selector returned an out-of-range index.');
        }
        return $index;
    }
}
