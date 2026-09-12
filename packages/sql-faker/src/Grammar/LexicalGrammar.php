<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use Closure;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Generates lexical values and realizes grammar terminals using fixed dialect definitions.
 * Output validity is checked against the target database; tokenizer identity is not a success condition.
 *
 * @visibility root
 */
interface LexicalGrammar
{
    /**
     * Writes the one lexeme a lexical generation plan asks for.
     *
     * @param GenerationPlan<bool> $plan Plan naming the lexeme kind and its bounds
     *
     * @return non-empty-string The lexeme
     */
    public function generate(GenerationPlan $plan): string;

    /**
     * Names the server version this grammar generates for.
     *
     * @return string Exact release identifier supplied by the dialect implementation
     */
    public function version(): string;

    /**
     * Identifies non-output parser markers for derivation budgets, independently of handler availability.
     */
    public function isNonOutput(string $terminal): bool;

    /**
     * Resolves lexical candidates and their boundary constraints into SQL.
     *
     * @param list<string> $terminals Terminals to write, in order
     * @param GenerationPlan<bool>|null $plan Plan that may pin exact lexemes for some terminals
     *
     * @return string SQL assembled from resolved lexical candidates
     * @throws LexicalException When no compatible lexical candidate is available
     */
    public function realize(array $terminals, ?GenerationPlan $plan = null): string;

    /**
     * Realizes terminal occurrences using their grammar context, without requiring tokenizer identity.
     * @param GenerationPlan<bool>|null $plan
     * @throws LexicalException When no applicable realization exists
     */
    public function realizeSequence(TerminalSequence $sequence, ?GenerationPlan $plan = null): string;

    /**
     * Resolves and exposes complete choices through the same lexical pipeline used to generate SQL.
     * @param GenerationPlan<bool>|null $plan
     * @param Closure(int): int $choose
     * @param (Closure(positive-int): ?int)|null $valueChoice Constructive values selected only while compiling a plan
     * @throws LexicalException When no applicable realization exists
     */
    public function resolveSequence(TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose, ?Closure $valueChoice = null): ResolvedOutput;
}
