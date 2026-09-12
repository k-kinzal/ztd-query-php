<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Lexeme;

/**
 * Allowed boundary spellings with every contributing rule retained for diagnostics.
 */
final class SpacingConstraint
{
    /**
     * Allows adjacent lexemes with no separator.
     */
    public const JOIN = 1;
    /**
     * Allows a single space between lexemes.
     */
    public const SPACE = 2;
    /**
     * Allows either supported separator form.
     */
    public const EITHER = 3;

    /**
     * @param list<string> $rules
     */
    public function __construct(public readonly int $allowed = self::EITHER, public readonly array $rules = [])
    {
    }

    /**
     * Keeps only separators allowed by both constraints and retains both sources.
     */
    public function intersect(self $other): self
    {
        return new self($this->allowed & $other->allowed, array_values(array_unique([...$this->rules, ...$other->rules])));
    }

    /**
     * Selects the default single space when permitted, a join otherwise, or null on contradiction.
     */
    public function separator(): ?string
    {
        if (($this->allowed & self::SPACE) !== 0) {
            return ' ';
        }
        return ($this->allowed & self::JOIN) !== 0 ? '' : null;
    }
}
