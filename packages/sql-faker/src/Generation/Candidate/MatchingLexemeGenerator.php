<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Candidate;

use Closure;
use Override;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;

/**
 * Applies its child only when its declared terminal or contextual predicate matches.
 */
final class MatchingLexemeGenerator implements LexemeGenerator
{
    /**
     * @param string|Closure(LexemeInput): bool $condition
     */
    public function __construct(
        private readonly string|Closure $condition,
        private readonly LexemeGenerator $generator,
    ) {
    }

    /**
     * Delegates only for the declared terminal and optional structural predicate; otherwise is non-applicable.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $matches = is_string($this->condition)
            ? $input->terminal()->name === $this->condition : ($this->condition)($input);
        return $matches ? $this->generator->generate($input) : null;
    }
}
