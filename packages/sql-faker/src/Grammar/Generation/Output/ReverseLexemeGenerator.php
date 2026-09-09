<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Output;

use Closure;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Value\ValueChoices;
use SqlFaker\Grammar\LexicalException;

/**
 * Chooses complete compatible candidates while traversing terminals from right to left.
 */
final class ReverseLexemeGenerator
{
    /**
     * Binds the candidate definitions, boundary resolver and exact release for diagnostics.
     */
    public function __construct(
        private readonly LexemeGenerator $lexemes,
        private readonly CandidateResolver $resolver,
        private readonly string $version,
        private readonly string $dialect = 'SQL',
    ) {
    }

    /**
     * @throws LexicalException When a candidate is missing, incompatible or unstable
     * @param GenerationPlan<bool>|null $plan
     * @param (Closure(positive-int): ?int)|null $valueChoice Explicit plan-time value decisions
     * @param Closure(int): int $choose Chooses once after each applicable candidate set has been evaluated
     */
    public function generate(TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose, ?Closure $valueChoice = null): ResolvedOutput
    {
        $occurrences = [];
        $requested = [];
        $original = [];
        foreach ($sequence->original as $terminal) {
            $occurrence = $occurrences[$terminal->name] ?? 0;
            $occurrences[$terminal->name] = $occurrence + 1;
            $original[$terminal->id] = $plan?->lexemeAt($terminal->name, $occurrence);
        }
        $occurrences = [];
        foreach ($sequence->terminals as $index => $terminal) {
            $occurrence = $occurrences[$terminal->name] ?? 0;
            $occurrences[$terminal->name] = $occurrence + 1;
            $requested[$index] = $plan?->lexemeAt($terminal->name, $occurrence) ?? $original[$terminal->id] ?? null;
        }
        $values = $valueChoice === null ? null : new ValueChoices($valueChoice);
        $completion = new BoundaryCompletion($this->lexemes, $this->resolver, $requested, values: $values);
        $right = new ResolvedOutput();
        for ($index = count($sequence->terminals) - 1; $index >= 0; --$index) {
            $right = $this->select(
                new LexemeInput($sequence, $index, $right, $requested[$index], $values),
                $choose,
                static fn (ResolvedOutput $suffix): bool => $completion->accepts($sequence, $index - 1, $suffix)
            );
        }
        if ($right->left !== null && $right->left->allowed !== SpacingConstraint::EITHER) {
            throw new LexicalException('Unresolved left boundary: ' . implode(', ', $right->left->rules));
        }
        return $right;
    }

    /**
     * @throws LexicalException When a candidate is missing, incompatible or unstable
     * @param Closure(int): int $choose
     * @param (Closure(ResolvedOutput): bool)|null $canComplete Checks outstanding left-boundary obligations
     */
    public function select(LexemeInput $input, Closure $choose, ?Closure $canComplete = null): ResolvedOutput
    {
        $candidates = $this->lexemes->generate($input);
        if ($candidates === null) {
            throw LexicalException::unsupportedTerminal($this->dialect, $this->version, $input->terminal()->name);
        }
        $eligible = 0;
        $contradictions = [];
        $rejections = [];
        foreach ($candidates->sequences() as $candidate) {
            if (!$this->matchesRequest($candidate, $input)) {
                continue;
            }
            $resolved = $this->resolver->resolve($candidate, $input);
            if ($resolved instanceof SpacingConstraint) {
                $contradictions[] = $candidate->id . ': ' . implode(', ', $resolved->rules);
                $rejections[] = ['index' => $input->index, 'candidate' => $candidate->id, 'rules' => $resolved->rules];
            } elseif ($canComplete !== null && !$canComplete($resolved)) {
                $rejections[] = ['index' => $input->index, 'candidate' => $candidate->id, 'rules' => ['uncompletable-left-boundary']];
            } else {
                ++$eligible;
            }
        }
        if ($eligible === 0) {
            throw new LexicalException('No compatible lexeme for ' . $input->terminal()->name . ' at ' . $input->index
                . ' in ' . $this->version . ' before ' . ($input->right->parts[0]->lexeme->text ?? '<end>')
                . '; ' . implode('; ', $contradictions));
        }
        $selected = $eligible === 1 ? 0 : $choose($eligible);
        if ($selected < 0 || $selected >= $eligible) {
            throw new LexicalException('Candidate selector returned an out-of-range index for ' . $input->terminal()->name);
        }
        foreach ($candidates->sequences() as $candidate) {
            if (!$this->matchesRequest($candidate, $input)) {
                continue;
            }
            $resolved = $this->resolver->resolve($candidate, $input);
            if ($resolved instanceof ResolvedOutput && ($canComplete === null || $canComplete($resolved)) && $selected-- === 0) {
                return new ResolvedOutput($resolved->parts, $resolved->left, $resolved->candidates, [...$resolved->rejections, ...$rejections]);
            }
        }
        throw new LexicalException('Lexeme candidates changed during selection for ' . $input->terminal()->name);
    }

    /**
     * Compares a requested spelling with the candidate's complete output, before boundary selection.
     */
    public function matchesRequest(LexemeSequence $candidate, LexemeInput $input): bool
    {
        return $input->requested === null
            || implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, $candidate->lexemes)) === $input->requested;
    }
}
