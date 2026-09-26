<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Plan;

use InvalidArgumentException;

/**
 * An immutable plan selecting the start rule, productions, lexemes and expansion limits.
 * Plans can be reused without carrying mutable choice cursors between generations.
 *
 * @template-covariant TRequiresNonEmpty of bool
 *
 * @visibility public
 * @example Keep a generation plan immutable and independent of earlier calls
 *     $plan = \SqlFaker\Generation\Plan\GenerationPlan::all()->withExpansionBudget(100);
 *     $plan->expansionBudget() // => 100
 */
final class GenerationPlan
{
    /**
     * Binds every choice a generation is directed by.
     * @param non-empty-string|null $startRule Rule the walk begins at, or null for the grammar entry point
     * @param array<string, non-empty-list<ProductionPattern>> $patterns Patterns directing each occurrence of a rule
     * @param array<string, ProductionPattern> $patternsForEveryOccurrence Pattern directing every further occurrence of a rule
     * @param array<string, array<int, string>> $lexemes Lexemes directing each occurrence of a terminal
     * @param non-empty-string|null $lexicalTarget Lexical rule to realize instead of walking the grammar
     * @param array<string, int> $parameters Parameters the lexical target is realized with
     * @param TRequiresNonEmpty $requiresNonEmpty Whether the walk must produce at least one symbol
     * @param bool $reserveSteps Whether to budget the remaining form and prefer fewer rule expansions
     * @param array<string, non-empty-list<string>> $candidateKeys Exact candidate semantics by terminal occurrence
     * @param array<string, RulePlan> $rules Scoped production, list and lexical conditions
     * @visibility SqlFaker\Generation\Choice
     */
    public function __construct(
        private readonly ?string $startRule,
        private readonly array $patterns,
        private readonly array $patternsForEveryOccurrence,
        private readonly array $lexemes,
        private readonly ?string $lexicalTarget,
        private readonly array $parameters,
        private readonly bool $requiresNonEmpty,
        private readonly int $maxDepth,
        private readonly bool $reserveSteps = false,
        private readonly ?int $expansionBudget = null,
        private readonly array $candidateKeys = [],
        private readonly array $rules = [],
    ) {
    }

    /**
     * Directs a walk over the whole grammar from its own entry point.
     * @return self<false> Plan that constrains nothing
     */
    public static function all(): self
    {
        return new self(null, [], [], [], null, [], false, PHP_INT_MAX);
    }

    /**
     * Directs a walk that begins at one rule instead of the grammar entry point.
     * @param string $startRule Rule the walk begins at
     * @return self<false> Plan restricted to that rule
     * @throws InvalidArgumentException When a required generation constraint is empty
     */
    public static function fromRule(string $startRule): self
    {
        if ($startRule === '') {
            throw new InvalidArgumentException('A generation plan start rule must not be empty.');
        }

        return new self($startRule, [], [], [], null, [], false, PHP_INT_MAX);
    }

    /**
     * Directs a walk that begins at one rule and takes the productions the caller named.
     * @param string $startRule Rule the walk begins at
     * @param array<string, non-empty-list<ProductionPattern>> $patterns Patterns directing each occurrence of a rule
     * @return self<false> Plan restricted to those productions
     * @throws InvalidArgumentException When a required generation constraint is empty
     */
    public static function constrained(string $startRule, array $patterns): self
    {
        if ($startRule === '') {
            throw new InvalidArgumentException('A generation plan start rule must not be empty.');
        }
        if ($patterns === []) {
            throw new InvalidArgumentException('A constrained generation plan requires production patterns.');
        }

        return new self($startRule, $patterns, [], [], null, [], false, PHP_INT_MAX);
    }

    /**
     * Directs the realization of one lexical rule instead of a walk over the grammar.
     * @param string $target Lexical rule to realize
     * @param array<string, int> $parameters Parameters the target is realized with
     * @return self<true> Plan that realizes that target
     * @throws InvalidArgumentException When a required generation constraint is empty
     */
    public static function lexical(string $target, array $parameters): self
    {
        if ($target === '') {
            throw new InvalidArgumentException('A lexical generation target must not be empty.');
        }

        return new self(null, [], [], [], $target, $parameters, true, PHP_INT_MAX);
    }

    /**
     * Answers a plan whose walk must produce at least one symbol.
     * @return self<true> Plan that refuses an empty result
     */
    public function requiringNonEmpty(): self
    {
        return new self(
            $this->startRule,
            $this->patterns,
            $this->patternsForEveryOccurrence,
            $this->lexemes,
            $this->lexicalTarget,
            $this->parameters,
            true,
            $this->maxDepth,
            $this->reserveSteps,
            $this->expansionBudget,
            $this->candidateKeys,
            $this->rules,
        );
    }

    /**
     * Answers a plan that spells each occurrence of a terminal the way the caller asked.
     * @param array<string, array<int, string>> $lexemes Lexemes directing each occurrence of a terminal
     * @return self<TRequiresNonEmpty> Plan carrying those lexemes
     * @throws InvalidArgumentException When a required generation constraint is empty
     */
    public function withLexemes(array $lexemes): self
    {
        if ($lexemes === []) {
            throw new InvalidArgumentException('A lexical generation plan requires lexemes.');
        }

        return $this->refine(lexemes: $lexemes);
    }

    /**
     * Answers a plan whose walk recurses no deeper than the caller allows.
     * @return self<TRequiresNonEmpty> Plan bounded to that depth
     */
    public function withMaxDepth(int $maxDepth): self
    {
        return $this->refine(maxDepth: max(1, $maxDepth));
    }

    /**
     * Adds subtree conditions without choosing unrelated grammar alternatives.
     * @return self<TRequiresNonEmpty>
     * @throws InvalidArgumentException When the rule or combined conditions are invalid
     */
    public function withRule(string $rule, RulePlan $plan): self
    {
        $rules = new RulePlan(rules: $this->rules);
        return $this->refine(rules: $rules->withRule($rule, $plan)->rules);
    }

    /**
     * Copies independent settings while preserving the start rule and output guarantee.
     * @param array<string, ProductionPattern>|null $patternsForEveryOccurrence
     * @param array<string, array<int, string>>|null $lexemes
     * @param array<string, non-empty-list<string>>|null $candidateKeys
     * @param array<string, RulePlan>|null $rules
     * @param positive-int|null $maxDepth
     * @param positive-int|null $expansionBudget
     * @return self<TRequiresNonEmpty>
     * @visibility namespace
     */
    public function refine(
        ?array $patternsForEveryOccurrence = null,
        ?array $lexemes = null,
        ?array $candidateKeys = null,
        ?array $rules = null,
        ?int $maxDepth = null,
        ?int $expansionBudget = null,
        ?bool $reserveSteps = null,
    ): self {
        return new self(
            $this->startRule,
            $this->patterns,
            $patternsForEveryOccurrence ?? $this->patternsForEveryOccurrence,
            $lexemes ?? $this->lexemes,
            $this->lexicalTarget,
            $this->parameters,
            $this->requiresNonEmpty,
            $maxDepth ?? $this->maxDepth,
            $reserveSteps ?? $this->reserveSteps,
            $expansionBudget ?? $this->expansionBudget,
            $candidateKeys ?? $this->candidateKeys,
            $rules ?? $this->rules,
        );
    }

    /**
     * @return array<string, RulePlan>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Answers the rule the walk begins at.
     * @return non-empty-string|null Rule the walk begins at, or null for the grammar entry point
     */
    public function startRule(): ?string
    {
        return $this->startRule;
    }

    /**
     * Answers the pattern directing one occurrence of a rule.
     * @param string $rule Rule the walk has reached
     * @param int $occurrence How many times the walk has reached it before
     * @return ProductionPattern|null Pattern to take, or null when the walk may choose freely
     */
    public function patternAt(string $rule, int $occurrence): ?ProductionPattern
    {
        return $this->patterns[$rule][$occurrence] ?? $this->patternsForEveryOccurrence[$rule] ?? null;
    }

    /**
     * Reports whether any occurrence-specific or recurring pattern can still constrain a continuation.
     * @param array<string, int> $occurrences
     */
    public function hasRemainingPatterns(array $occurrences): bool
    {
        if ($this->patternsForEveryOccurrence !== []) {
            return true;
        }
        foreach ($this->patterns as $rule => $patterns) {
            if (($occurrences[$rule] ?? 0) < count($patterns)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Caps counters only once later occurrences use the same default pattern, making recursive states finite.
     * @param array<string, int> $occurrences
     * @return array<string, int>
     */
    public function patternState(array $occurrences): array
    {
        $state = [];
        foreach (array_unique([...array_keys($this->patterns), ...array_keys($this->patternsForEveryOccurrence)]) as $rule) {
            $state[$rule] = min($occurrences[$rule] ?? 0, count($this->patterns[$rule] ?? []));
        }
        return $state;
    }

    /**
     * Answers a plan that directs every further occurrence of one rule the same way.
     * @param string $rule Rule to direct
     * @param ProductionPattern $pattern Pattern every occurrence not named directly takes
     * @return self<TRequiresNonEmpty> Plan carrying that fallback
     * @throws InvalidArgumentException When a required generation constraint is empty
     */
    public function withPatternForEveryOccurrence(string $rule, ProductionPattern $pattern): self
    {
        if ($rule === '') {
            throw new InvalidArgumentException('A generation plan rule must not be empty.');
        }

        return $this->refine(patternsForEveryOccurrence: [...$this->patternsForEveryOccurrence, $rule => $pattern]);
    }

    /**
     * Answers the lexeme one occurrence of a terminal is spelled with.
     * @param string $terminal Terminal the walk has reached
     * @param int $occurrence How many times the walk has reached it before
     * @return string|null Lexeme to write, or null when the walk may choose freely
     */
    public function lexemeAt(string $terminal, int $occurrence): ?string
    {
        return $this->lexemes[$terminal][$occurrence] ?? null;
    }

    /**
     * Answers the lexical rule to realize instead of walking the grammar.
     * @return non-empty-string|null Lexical rule to realize, or null when the grammar is walked
     */
    public function lexicalTarget(): ?string
    {
        return $this->lexicalTarget;
    }

    /**
     * Answers the parameters the lexical target is realized with.
     * @return array<string, int> Parameters by name
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Answers whether the walk must produce at least one symbol.
     * @return TRequiresNonEmpty True when an empty result is refused
     */
    public function requiresNonEmpty(): bool
    {
        return $this->requiresNonEmpty;
    }

    /**
     * Answers how deep the walk may recurse.
     */
    public function maxDepth(): int
    {
        return $this->maxDepth;
    }
    /**
     * Reserves enough derivation steps to finish the entire remaining form.
     * @return self<TRequiresNonEmpty> Plan with a bounded completion policy
     */
    public function withStepBudget(): self
    {
        return $this->refine(reserveSteps: true);
    }

    /**
     * Reports whether production choice reserves steps for the remaining form.
     */
    public function usesStepBudget(): bool
    {
        return $this->reserveSteps;
    }

    /**
     * Bounds total expansions independently of the legacy depth policy.
     * @return self<TRequiresNonEmpty>
     * @throws InvalidArgumentException When no expansion is permitted
     */
    public function withExpansionBudget(int $budget): self
    {
        if ($budget < 1) {
            throw new InvalidArgumentException('Expansion budget must be positive.');
        }
        return $this->refine(expansionBudget: $budget);
    }

    /**
     * Pins candidate semantics as well as spelling, including empty marker candidates.
     * @param array<string, non-empty-list<string>> $keys
     * @return self<TRequiresNonEmpty>
     */
    public function withCandidateKeys(array $keys): self
    {
        return $this->refine(candidateKeys: $keys);
    }

    /**
     * Returns the exact candidate semantics requested for one terminal occurrence.
     */
    public function candidateKeyAt(string $terminal, int $occurrence): ?string
    {
        return $this->candidateKeys[$terminal][$occurrence] ?? null;
    }

    /**
     * Returns the explicit total expansion budget, if configured.
     */
    public function expansionBudget(): ?int
    {
        return $this->expansionBudget;
    }

    /**
     * Directs a bounded walk that must produce a statement.
     * @param non-empty-string|null $startRule Rule the statement is grown from, or null for the grammar entry point
     * @return self<true> Plan for one bounded, non-empty statement
     */
    public static function statement(?string $startRule, int $maxDepth): self
    {
        $plan = $startRule === null
            ? self::all()
            : self::fromRule($startRule);

        return $plan->requiringNonEmpty()->withMaxDepth($maxDepth);
    }
}
