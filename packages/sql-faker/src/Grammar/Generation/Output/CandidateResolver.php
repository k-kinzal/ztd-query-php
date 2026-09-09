<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Output;

use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;

/**
 * Evaluates one complete candidate against its internal and external boundaries.
 */
final class CandidateResolver
{
    /**
     * Binds the composed boundary rules independently of candidate selection.
     */
    public function __construct(private readonly SpacingRule $spacing)
    {
    }

    /**
     * @return ResolvedOutput|SpacingConstraint A contradiction is returned without changing the input.
     */
    public function resolve(LexemeSequence $sequence, LexemeInput $input): ResolvedOutput|SpacingConstraint
    {
        $parts = $input->right->parts;
        $pending = $input->right->left;
        $lastBoundary = $sequence->boundaries[count($sequence->lexemes)] ?? null;
        if ($lastBoundary !== null) {
            if ($parts === [] && $lastBoundary->allowed !== SpacingConstraint::EITHER) {
                return new SpacingConstraint(0, [...$lastBoundary->rules, 'missing-right-neighbor']);
            }
            $pending = ($pending ?? new SpacingConstraint())->intersect($lastBoundary);
        }
        for ($index = count($sequence->lexemes) - 1; $index >= 0; --$index) {
            $lexeme = $sequence->lexemes[$index];
            $constraint = $pending ?? new SpacingConstraint();
            $right = $parts[0]->lexeme ?? null;
            if ($right !== null) {
                $applied = $this->spacing->apply(new LexemeBoundary($lexeme, $right), $input);
                $constraint = $constraint->intersect($applied ?? new SpacingConstraint());
            }
            $separator = $constraint->separator();
            if ($separator === null) {
                return $constraint;
            }
            array_unshift($parts, new OutputPart($lexeme, $right === null ? '' : $separator, $sequence->id, $constraint->rules, $constraint->allowed));
            $pending = $sequence->boundaries[$index] ?? null;
        }
        if ($sequence->left !== null) {
            $pending = ($pending ?? new SpacingConstraint())->intersect($sequence->left);
        }
        if ($input->index === 0 && $pending !== null && $pending->allowed !== SpacingConstraint::EITHER) {
            return new SpacingConstraint(0, [...$pending->rules, 'missing-left-neighbor']);
        }
        return new ResolvedOutput($parts, $pending, [$sequence, ...$input->right->candidates], $input->right->rejections);
    }
}
