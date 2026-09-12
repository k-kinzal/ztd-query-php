<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Output;

use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Generation\Value\ValueChoices;

/**
 * Discharges explicit left-boundary obligations before a candidate is committed.
 * Traversal stops when the obligation is discharged; it never retries completed SQL.
 */
final class BoundaryCompletion
{
    /**
     * @param array<int, string|null> $requests Planned spellings indexed by rewritten occurrence
     * @param array<int, string|null> $keys Planned candidate semantics indexed by rewritten occurrence
     */
    public function __construct(
        private readonly LexemeGenerator $lexemes,
        private readonly CandidateResolver $resolver,
        private readonly array $requests = [],
        private readonly array $keys = [],
        private readonly ?ValueChoices $values = null,
    ) {
    }

    /**
     * Finds a witness for each pending boundary across compounds and empty markers using the same memoized value decisions as final realization.
     */
    public function accepts(TerminalSequence $sequence, int $index, ResolvedOutput $right): bool
    {
        if (($right->left->allowed ?? SpacingConstraint::EITHER) === SpacingConstraint::EITHER) {
            return true;
        }
        if ($index < 0) {
            return false;
        }
        $input = new LexemeInput($sequence, $index, $right, $this->requests[$index] ?? null, $this->values);
        $candidates = $this->lexemes->generate($input);
        if ($candidates === null) {
            return false;
        }
        foreach ($candidates->sequences() as $candidate) {
            $key = $this->keys[$index] ?? null;
            if (($input->requested !== null && implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, $candidate->lexemes)) !== $input->requested)
                || ($key !== null && $candidate->key() !== $key)) {
                continue;
            }
            $resolved = $this->resolver->resolve($candidate, $input);
            if ($resolved instanceof ResolvedOutput && $this->accepts($sequence, $index - 1, $resolved)) {
                return true;
            }
        }
        return false;
    }
}
