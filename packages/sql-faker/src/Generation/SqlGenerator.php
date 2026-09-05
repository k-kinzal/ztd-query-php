<?php

declare(strict_types=1);

namespace SqlFaker\Generation;

use Closure;
use Faker\Generator;
use SqlFaker\Grammar\Derivation;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;

/**
 * Generates SQL from a common grammar and a plan supplied by the caller.
 *
 * Dialect spelling and parser semantic actions are supplied at construction;
 * the derivation, retry budget and non-empty guarantee are shared by all grammars.
 */
final class SqlGenerator
{
    private const LEXICAL_ATTEMPT_LIMIT = 32;

    private readonly TerminationAnalyzer $analyzer;

    /**
     * @param Grammar $grammar Common AST to derive from
     * @param Generator $faker Source of random production choices
     * @param LexicalGrammar $lexicalGrammar Writes and verifies terminal sequences
     * @param (Closure(list<string>): list<string>)|null $normalize Parser semantic constraints, when needed
     * @param (Closure(string|null): string)|null $startSymbol Resolves version-specific rule names, when needed
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly Generator $faker,
        private readonly LexicalGrammar $lexicalGrammar,
        private readonly ?Closure $normalize = null,
        private readonly ?Closure $startSymbol = null,
    ) {
        $this->analyzer = new TerminationAnalyzer($grammar, $lexicalGrammar->supports(...));
    }

    /**
     * Derives the requested productions and realizes their terminals as SQL.
     *
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan Start rule, constraints and lexical witnesses
     * @return (TRequiresNonEmpty is true ? non-empty-string : string) Generated SQL
     *
     * @throws GenerationException When the grammar or plan cannot produce the requested output
     * @throws LexicalException When all lexical realization attempts fail
     */
    public function generate(GenerationPlan $plan): string
    {
        if ($plan->lexicalTarget() !== null) {
            return $this->lexicalGrammar->generate($plan);
        }
        $start = $this->startSymbol !== null
            ? ($this->startSymbol)($plan->startRule())
            : ($plan->startRule() ?? $this->grammar->startSymbol);
        $attempt = 0;
        do {
            $terminals = (new Derivation($this->grammar, $this->faker, $this->analyzer))->of($start, $plan);
            $names = array_map(static fn (Terminal $terminal): string => $terminal->value, $terminals);
            if ($this->normalize !== null) {
                $names = ($this->normalize)($names);
            }
            try {
                $sql = $this->lexicalGrammar->realize($names, $plan);
                if ($sql !== '' || !$plan->requiresNonEmpty()) {
                    return $sql;
                }
                $lastException = GenerationException::planRequiresNonEmptyOutput($this->lexicalGrammar->version());
            } catch (LexicalException $exception) {
                $lastException = $exception;
            }
        } while (++$attempt < self::LEXICAL_ATTEMPT_LIMIT);

        throw $lastException;
    }
}
