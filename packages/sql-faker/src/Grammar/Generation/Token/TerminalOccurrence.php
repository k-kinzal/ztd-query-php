<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Token;

/**
 * A terminal occurrence, including the productions that introduced it.
 */
final class TerminalOccurrence
{
    /**
     * @param list<int> $ancestors Derivation occurrence IDs, outermost first
     * @param list<string> $rules Grammar rules, outermost first
     */
    public function __construct(
        public readonly string $name,
        public readonly int $id,
        public readonly array $ancestors = [],
        public readonly array $rules = [],
        public readonly ?string $rewrite = null,
    ) {
    }

    /**
     * Reports whether a grammar rule encloses this occurrence.
     */
    public function within(string $rule): bool
    {
        return in_array($rule, $this->rules, true);
    }

    /**
     * Identifies the nearest enclosing occurrence of a grammar rule, including recursive scopes.
     */
    public function ancestor(string $rule): ?int
    {
        for ($index = count($this->rules) - 1; $index >= 0; --$index) {
            if ($this->rules[$index] === $rule) {
                return $this->ancestors[$index] ?? null;
            }
        }
        return null;
    }

    /**
     * Returns a renamed occurrence while preserving its original identity and enclosing productions.
     */
    public function replaced(string $name, string $rewrite): self
    {
        return new self($name, $this->id, $this->ancestors, $this->rules, $rewrite);
    }
}
