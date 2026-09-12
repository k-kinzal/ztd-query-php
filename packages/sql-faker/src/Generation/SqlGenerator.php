<?php

declare(strict_types=1);

namespace SqlFaker\Generation;

use Closure;
use Faker\Generator;
use SqlFaker\Generation\Derivation\TokenGenerator;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Lexeme\LexicalGrammar;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Model\Grammar;

/**
 * Derives terminals, rewrites structural constraints, and realizes lexemes once.
 * Dialect definitions supply the syntax and boundary rules; no completed SQL is retried or repaired.
 *
 * @visibility root
 */
final class SqlGenerator
{
    private ?TokenGenerator $tokens = null;

    /**
     * The latest terminal sequence retains both the original grammar choices and their rewrites.
     */
    public ?TerminalSequence $lastSequence = null;

    /**
     * Binds grammar, lexical definitions and structural rules before generation starts.
     * @param (Closure(string|null): string)|null $startSymbol Resolves explicitly requested release aliases
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly Generator $faker,
        private readonly LexicalGrammar $lexicalGrammar,
        private readonly ?TokenRewriter $rewriter = null,
        private readonly ?Closure $startSymbol = null,
    ) {
    }

    /**
     * Generates once through the declared stages; candidate absence remains an error.
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     * @throws GenerationException When the grammar or plan cannot produce the requested output
     * @throws LexicalException When no applicable lexical realization exists
     */
    public function generate(GenerationPlan $plan): string
    {
        $this->lastSequence = null;
        if ($plan->lexicalTarget() !== null) {
            return $this->lexicalGrammar->generate($plan);
        }
        $requested = $plan->startRule();
        $root = $requested === null ? $this->grammar->startSymbol
            : ($this->startSymbol !== null ? ($this->startSymbol)($requested) : $requested);
        $tokens = ($this->tokens ??= new TokenGenerator($this->grammar, $this->faker, $this->lexicalGrammar->isNonOutput(...)))->generate($root, $plan);
        $this->lastSequence = $this->rewriter?->rewrite($tokens) ?? $tokens;
        $sql = $this->lexicalGrammar->realizeSequence($this->lastSequence, $plan);
        if ($sql === '' && $plan->requiresNonEmpty()) {
            throw GenerationException::planRequiresNonEmptyOutput($this->lexicalGrammar->version());
        }
        return $sql;
    }
}
