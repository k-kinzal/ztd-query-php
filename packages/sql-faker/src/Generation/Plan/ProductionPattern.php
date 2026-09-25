<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Plan;

/**
 * Narrows a rule to the alternatives a caller will accept.
 *
 * A grammar rule offers every alternative its parser recognises, and a caller
 * generating a specific statement usually wants one of them. A pattern says
 * which, either by naming symbols the alternative must contain, by naming the
 * alternative outright, or by asking only that it write something.
 *
 * @visibility root
 */
final class ProductionPattern
{
    private const CONTAINING = 'containing';
    private const EXACT = 'exact';
    private const NON_EMPTY = 'non-empty';
    private const ORDINAL = 'ordinal';
    private const ANY = 'any';
    private const ALL = 'all';
    private const NOT = 'not';

    /**
     * @param array<array-key, string> $symbols Symbols the pattern is written in terms of
     * @param self::CONTAINING|self::EXACT|self::NON_EMPTY|self::ORDINAL|self::ANY|self::ALL|self::NOT $mode How those symbols are compared against an alternative
     * @param list<self> $patterns
     */
    public function __construct(
        private readonly array $symbols,
        private readonly string $mode,
        private readonly ?int $ordinal = null,
        private readonly array $patterns = [],
    ) {
    }

    /**
     * Matches any alternative that contains all the named symbols.
     *
     * @param string ...$symbols Symbols the alternative must contain
     *
     * @return self Pattern matching on containment
     */
    public static function containing(string ...$symbols): self
    {
        return new self($symbols, self::CONTAINING);
    }

    /**
     * Matches only the alternative written exactly as the named symbols.
     *
     * @param string ...$symbols Symbols the alternative is made of, in order
     *
     * @return self Pattern matching one alternative
     */
    public static function exactly(string ...$symbols): self
    {
        return new self(array_values($symbols), self::EXACT);
    }

    /**
     * Selects one alternative, including when several have identical symbols.
     */
    public static function at(int $ordinal): self
    {
        return new self([], self::ORDINAL, $ordinal);
    }

    /**
     * Matches any alternative that writes at least one symbol.
     *
     * @return self Pattern refusing only the empty alternative
     */
    public static function nonEmpty(): self
    {
        return new self([], self::NON_EMPTY);
    }

    /**
     * Retains alternatives accepted by at least one condition.
     */
    public static function anyOf(self $first, self ...$others): self
    {
        return new self([], self::ANY, patterns: [$first, ...array_values($others)]);
    }

    /**
     * Requires every condition at the same production.
     */
    public static function allOf(self $first, self ...$others): self
    {
        return new self([], self::ALL, patterns: [$first, ...array_values($others)]);
    }

    /**
     * Excludes a condition without enumerating all other productions.
     */
    public static function excluding(self $pattern): self
    {
        return new self([], self::NOT, patterns: [$pattern]);
    }

    /**
     * @param list<string> $symbols
     */
    public function matches(array $symbols, ?int $ordinal = null): bool
    {
        if ($this->mode === self::ANY || $this->mode === self::ALL || $this->mode === self::NOT) {
            $matches = array_map(static fn (self $pattern): bool => $pattern->matches($symbols, $ordinal), $this->patterns);
            return match ($this->mode) {
                self::ANY => in_array(true, $matches, true),
                self::ALL => !in_array(false, $matches, true),
                self::NOT => !($matches[0] ?? false),
            };
        }
        if ($this->mode === self::ORDINAL) {
            return $ordinal !== null && $ordinal === $this->ordinal;
        }
        if ($this->mode === self::EXACT) {
            return $symbols === $this->symbols;
        }
        if ($this->mode === self::NON_EMPTY) {
            return $symbols !== [];
        }
        foreach ($this->symbols as $required) {
            if (!in_array($required, $symbols, true)) {
                return false;
            }
        }

        return true;
    }
}
