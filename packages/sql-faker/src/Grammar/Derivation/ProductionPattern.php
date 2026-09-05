<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

/**
 * Narrows a rule to the alternatives a caller will accept.
 *
 * A grammar rule offers every alternative its parser recognises, and a caller
 * generating a specific statement usually wants one of them. A pattern says
 * which, either by naming symbols the alternative must contain, by naming the
 * alternative outright, or by asking only that it write something.
 */
final class ProductionPattern
{
    private const CONTAINING = 'containing';
    private const EXACT = 'exact';
    private const NON_EMPTY = 'non-empty';

    /**
     * @param array<array-key, string> $symbols Symbols the pattern is written in terms of
     * @param self::CONTAINING|self::EXACT|self::NON_EMPTY $mode How those symbols are compared against an alternative
     */
    public function __construct(
        private readonly array $symbols,
        private readonly string $mode,
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
     * Matches any alternative that writes at least one symbol.
     *
     * @return self Pattern refusing only the empty alternative
     */
    public static function nonEmpty(): self
    {
        return new self([], self::NON_EMPTY);
    }

    /**
     * @param list<string> $symbols
     */
    public function matches(array $symbols): bool
    {
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
