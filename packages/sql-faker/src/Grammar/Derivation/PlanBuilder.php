<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

use Closure;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;

/**
 * Constructs explicit production, lexeme and separator instructions before generation.
 */
final class PlanBuilder
{
    private readonly CompletionCosts $costs;

    /**
     * @param (Closure(list<string>): list<string>)|null $normalize
     * @param (Closure(string|null): string)|null $startSymbol Resolves explicitly requested release aliases
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly LexicalGrammar $lexical,
        private readonly ?Closure $normalize = null,
        private readonly ?Closure $startSymbol = null,
    ) {
        $this->costs = new CompletionCosts($grammar, $lexical->supports(...), $lexical->spellings(...));
    }

    /**
     * @param GenerationPlan<bool> $plan
     */
    public function minimumExpansions(GenerationPlan $plan): int
    {
        return $this->costs->rule($this->root($plan), $plan->requiresNonEmpty());
    }

    /**
     * Uses the grammar entry point unless the caller explicitly requests a rule.
     * @param GenerationPlan<bool> $plan
     */
    public function root(GenerationPlan $plan): string
    {
        $requested = $plan->startRule();
        return $requested === null ? $this->grammar->startSymbol
            : ($this->startSymbol !== null ? ($this->startSymbol)($requested) : $requested);
    }

    /**
     * @template T of bool
     * @param GenerationPlan<T> $constraints
     * @param Closure(int): ?int $productionChoice
     * @param Closure(int): ?int $lexicalChoice
     * @return GenerationPlan<T>
     */
    public function build(GenerationPlan $constraints, int $budget, Closure $productionChoice, Closure $lexicalChoice): GenerationPlan
    {
        [$patterns, $terminals] = $this->derive($constraints, $budget, $productionChoice);
        $names = array_map(static fn (Terminal $terminal): string => $terminal->value, $terminals);
        if ($this->normalize !== null) {
            $names = ($this->normalize)($names);
        }
        $lexemes = [];
        foreach ($names as $name) {
            $occurrence = count($lexemes[$name] ?? []);
            $lexemes[$name][] = $constraints->lexemeAt($name, $occurrence) ?? $this->spelling($name, $lexicalChoice);
        }
        $trivia = [[], []];
        for ($index = 0; $index <= count($names); ++$index) {
            $trivia[0][] = $constraints->triviaAt($index, false) ?? $this->spelling('@TRIVIA', $lexicalChoice);
            $trivia[1][] = $constraints->triviaAt($index, true)
                ?? (($lexicalChoice(2) ?? 0) === 0 ? '' : $this->spelling('@TRIVIA', $lexicalChoice));
        }
        return new GenerationPlan(
            $constraints->startRule(),
            $patterns,
            [],
            $lexemes,
            null,
            [],
            $constraints->requiresNonEmpty(),
            $constraints->maxDepth(),
            $constraints->usesStepBudget(),
            $budget,
            $trivia
        );
    }

    /**
     * @param GenerationPlan<bool> $plan
     * @param Closure(int): ?int $choose
     * @return array{array<string, non-empty-list<ProductionPattern>>, list<Terminal>}
     * @throws GenerationException When the constraints cannot be completed
     */
    public function derive(GenerationPlan $plan, int $budget, Closure $choose): array
    {
        $pending = [new NonTerminal($this->root($plan))];
        $patterns = [];
        $terminals = [];
        while ($pending !== []) {
            $symbol = array_shift($pending);
            if ($symbol instanceof Terminal) {
                $terminals[] = $symbol;
                continue;
            }
            if (--$budget < 0) {
                throw GenerationException::derivationLimitExceeded();
            }
            $name = $symbol->value();
            $rule = $this->grammar->ruleMap[$name] ?? throw GenerationException::unknownRule($name);
            $pattern = $plan->patternAt($name, count($patterns[$name] ?? []));
            $alternatives = [];
            foreach ($rule->alternatives as $ordinal => $production) {
                if ($pattern === null || $pattern->matches(array_map(static fn (Symbol $s): string => $s->value(), $production->symbols), $ordinal)) {
                    $alternatives[] = $production;
                }
            }
            $production = $this->select($alternatives, $pending, $this->costs->sequence($terminals)[1] === PHP_INT_MAX && $plan->requiresNonEmpty(), $budget, $choose);
            $ordinal = array_search($production, $rule->alternatives, true);
            $patterns[$name][] = ProductionPattern::at($ordinal === false ? 0 : $ordinal);
            $pending = [...$production->symbols, ...$pending];
        }
        return [$patterns, $terminals];
    }

    /**
     * @param list<Production> $alternatives
     * @param list<Symbol> $pending
     * @param Closure(int): ?int $choose
     * @throws GenerationException When no alternative can finish within the budget
     */
    public function select(array $alternatives, array $pending, bool $nonEmpty, int $budget, Closure $choose): Production
    {
        $candidates = $this->costs->affordable($alternatives, $pending, $nonEmpty, $budget);
        if ($candidates === []) {
            throw GenerationException::derivationLimitExceeded();
        }
        $index = $choose(count($candidates));
        if ($index === null) {
            $costs = array_map(fn (Production $p): int => $this->costs->completion($p, $pending, $nonEmpty), $candidates);
            $index = array_search(min($costs), $costs, true);
        }
        return $candidates[$index === false ? 0 : $index];
    }

    /**
     * @param Closure(int): ?int $choose
     * @throws LexicalException When a terminal has no concrete spelling
     */
    public function spelling(string $terminal, Closure $choose): string
    {
        $spellings = $this->lexical->spellings($terminal);
        if ($spellings === []) {
            throw LexicalException::unsupportedTerminal($this->lexical->version(), $this->lexical->version(), $terminal);
        }
        return $spellings[$choose(count($spellings)) ?? 0];
    }
}
