<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

/**
 * A part of a translated regular expression: its PCRE source and how many characters it matches.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Fragment
{
    /**
     * @param string $source The PCRE source, a unit a quantifier can follow
     * @param int $minimum The fewest characters it matches
     * @param int|null $maximum The most characters it matches, or null for no bound
     * @param bool $quantifiable Whether a quantifier may follow it
     * @param string|null $folded The full case folding of a literal character matched without regard to case
     */
    public function __construct(public readonly string $source, public readonly int $minimum = 1, public readonly ?int $maximum = 1, public readonly bool $quantifiable = true, public readonly ?string $folded = null)
    {
    }

    /**
     * Answers a fragment that matches no character: an anchor, an assertion or a change of mode.
     */
    public static function empty(string $source, bool $quantifiable = false): self
    {
        return new self($source, 0, 0, $quantifiable);
    }

    /**
     * Answers the fragments one after another.
     *
     * @param list<self> $parts
     */
    public static function sequence(array $parts): self
    {
        $minimum = 0;
        $maximum = 0;
        foreach ($parts as $part) {
            $minimum += $part->minimum;
            $maximum = $maximum === null || $part->maximum === null ? null : $maximum + $part->maximum;
        }

        return new self(implode('', array_map(static fn (self $part): string => $part->source, $parts)), $minimum, $maximum);
    }

    /**
     * Answers the alternatives of a choice.
     *
     * @param non-empty-list<self> $branches
     */
    public static function choice(array $branches): self
    {
        $minimum = min(array_map(static fn (self $branch): int => $branch->minimum, $branches));
        $maximum = 0;
        foreach ($branches as $branch) {
            $maximum = $maximum === null || $branch->maximum === null ? null : max($maximum, $branch->maximum);
        }

        return new self(implode('|', array_map(static fn (self $branch): string => $branch->source, $branches)), $minimum, $maximum);
    }
}
