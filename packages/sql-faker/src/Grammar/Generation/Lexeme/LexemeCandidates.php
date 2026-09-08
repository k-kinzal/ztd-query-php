<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Lexeme;

use Closure;

/**
 * A repeatable lazy candidate stream. An empty stream is distinct from non-applicability.
 */
final class LexemeCandidates
{
    /**
     * @param Closure(): iterable<int, LexemeSequence> $candidates
     */
    public function __construct(private readonly Closure $candidates)
    {
    }

    /**
     * Creates a repeatable candidate set from zero or more complete sequences.
     */
    public static function of(LexemeSequence ...$candidates): self
    {
        return new self(static fn (): array => array_values($candidates));
    }

    /**
     * @return iterable<int, LexemeSequence>
     */
    public function sequences(): iterable
    {
        return ($this->candidates)();
    }
}
