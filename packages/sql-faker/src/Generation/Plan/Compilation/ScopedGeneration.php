<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Plan\Compilation;

use Closure;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Resolves lexical conditions after structural rewriting, retaining ancestor identity for inserted terminals.
 */
final class ScopedGeneration
{
    /**
     * @param array<int, array<string, LexemeConstraint>> $bindings
     */
    public function __construct(public readonly TerminalSequence $sequence, private readonly array $bindings = [])
    {
    }

    /**
     * @template T of bool
     * @param GenerationPlan<T> $plan
     * @param Closure(positive-int): int $choose
     * @return GenerationPlan<T>
     * @throws LexicalException When an explicit lexeme violates a scoped condition
     */
    public function lexicalPlan(TerminalSequence $sequence, GenerationPlan $plan, Closure $choose): GenerationPlan
    {
        $lexemes = [];
        $occurrences = [];
        $original = [];
        foreach ($sequence->original as $terminal) {
            $occurrence = $occurrences[$terminal->name] ?? 0;
            $occurrences[$terminal->name] = $occurrence + 1;
            $original[$terminal->id] = $plan->lexemeAt($terminal->name, $occurrence);
        }
        $occurrences = [];
        foreach ($sequence->terminals as $terminal) {
            $occurrence = $occurrences[$terminal->name] ?? 0;
            $occurrences[$terminal->name] = $occurrence + 1;
            $value = $plan->lexemeAt($terminal->name, $occurrence) ?? $original[$terminal->id] ?? null;
            $constraint = $this->constraint($terminal);
            if ($constraint !== null) {
                if ($value !== null && !$constraint->accepts($value)) {
                    throw new LexicalException('Explicit lexeme contradicts the plan for ' . $terminal->name . '.');
                }
                $value ??= $constraint->choose($choose);
            }
            if ($value !== null) {
                $lexemes[$terminal->name][$occurrence] = $value;
            }
        }
        return $lexemes === [] ? $plan : $plan->withLexemes($lexemes);
    }

    /**
     * Finds the nearest applicable lexical scope, including for rewritten terminals.
     */
    public function constraint(TerminalOccurrence $terminal): ?LexemeConstraint
    {
        foreach (array_reverse($terminal->ancestors) as $ancestor) {
            if (isset($this->bindings[$ancestor][$terminal->name])) {
                return $this->bindings[$ancestor][$terminal->name];
            }
        }
        return null;
    }
}
