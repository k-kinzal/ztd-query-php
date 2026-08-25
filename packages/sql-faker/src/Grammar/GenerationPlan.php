<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Directs one act of generation: where to start, which productions to take, and how deep to go.
 *
 * A generator walks a grammar that describes far more SQL than any single
 * caller wants, so the plan is what narrows it. Every plan is immutable and
 * every refinement answers a new one, which is what lets one plan be reused
 * across generations without a previous walk leaking into the next.
 *
 * @template-covariant TRequiresNonEmpty of bool
 */
final class GenerationPlan
{
    /**
     * Binds every choice a generation is directed by.
     *
     * A start rule is either absent or names a rule, never the empty string,
     * and the same holds for a lexical target. Both say so in their type, so a
     * plan that could not direct anything cannot be written in the first place.
     *
     * @param non-empty-string|null $startRule Rule the walk begins at, or null for the grammar entry point
     * @param array<string, non-empty-list<ProductionPattern>> $patterns Patterns directing each occurrence of a rule
     * @param array<string, ProductionPattern> $patternsForEveryOccurrence Pattern directing every further occurrence of a rule
     * @param array<string, non-empty-list<non-empty-string>> $lexemes Lexemes directing each occurrence of a terminal
     * @param non-empty-string|null $lexicalTarget Lexical rule to realize instead of walking the grammar
     * @param array<string, int> $parameters Parameters the lexical target is realized with
     * @param TRequiresNonEmpty $requiresNonEmpty Whether the walk must produce at least one symbol
     * @param int $maxDepth How deep the walk may recurse
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
    ) {
    }

    /**
     * Directs a walk over the whole grammar from its own entry point.
     *
     * @return self<false> Plan that constrains nothing
     */
    public static function all(): self
    {
        return new self(null, [], [], [], null, [], false, PHP_INT_MAX);
    }

    /**
     * Directs a walk that begins at one rule instead of the grammar entry point.
     *
     * @param non-empty-string $startRule Rule the walk begins at
     *
     * @return self<false> Plan restricted to that rule
     */
    public static function fromRule(string $startRule): self
    {
        return new self($startRule, [], [], [], null, [], false, PHP_INT_MAX);
    }

    /**
     * Directs a walk that begins at one rule and takes the productions the caller named.
     *
     * @param non-empty-string $startRule Rule the walk begins at
     * @param non-empty-array<string, non-empty-list<ProductionPattern>> $patterns Patterns directing each occurrence of a rule
     *
     * @return self<false> Plan restricted to those productions
     */
    public static function constrained(string $startRule, array $patterns): self
    {
        return new self($startRule, $patterns, [], [], null, [], false, PHP_INT_MAX);
    }

    /**
     * Directs the realization of one lexical rule instead of a walk over the grammar.
     *
     * @param non-empty-string $target Lexical rule to realize
     * @param array<string, int> $parameters Parameters the target is realized with
     *
     * @return self<true> Plan that realizes that target
     */
    public static function lexical(string $target, array $parameters): self
    {
        return new self(null, [], [], [], $target, $parameters, true, PHP_INT_MAX);
    }

    /**
     * Answers a plan whose walk must produce at least one symbol.
     *
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
        );
    }

    /**
     * Answers a plan that spells each occurrence of a terminal the way the caller asked.
     *
     * @param non-empty-array<string, non-empty-list<non-empty-string>> $lexemes Lexemes directing each occurrence of a terminal
     *
     * @return self<TRequiresNonEmpty> Plan carrying those lexemes
     */
    public function withLexemes(array $lexemes): self
    {
        return new self(
            $this->startRule,
            $this->patterns,
            $this->patternsForEveryOccurrence,
            $lexemes,
            $this->lexicalTarget,
            $this->parameters,
            $this->requiresNonEmpty,
            $this->maxDepth,
        );
    }

    /**
     * Answers a plan whose walk recurses no deeper than the caller allows.
     *
     * A grammar rule can reach itself, so without a limit a walk over a
     * recursive rule need not terminate. A depth below one would forbid the
     * walk from taking any production at all, so it is raised to one.
     *
     * @param int $maxDepth How deep the walk may recurse
     *
     * @return self<TRequiresNonEmpty> Plan bounded to that depth
     */
    public function withMaxDepth(int $maxDepth): self
    {
        return new self(
            $this->startRule,
            $this->patterns,
            $this->patternsForEveryOccurrence,
            $this->lexemes,
            $this->lexicalTarget,
            $this->parameters,
            $this->requiresNonEmpty,
            max(1, $maxDepth),
        );
    }

    /**
     * Answers the rule the walk begins at.
     *
     * @return non-empty-string|null Rule the walk begins at, or null for the grammar entry point
     */
    public function startRule(): ?string
    {
        return $this->startRule;
    }

    /**
     * Answers the pattern directing one occurrence of a rule.
     *
     * An occurrence the caller named directly wins over the pattern standing
     * for every occurrence, so a plan can direct the first occurrence one way
     * and everything after it another.
     *
     * @param string $rule Rule the walk has reached
     * @param int $occurrence How many times the walk has reached it before
     *
     * @return ProductionPattern|null Pattern to take, or null when the walk may choose freely
     */
    public function patternAt(string $rule, int $occurrence): ?ProductionPattern
    {
        return $this->patterns[$rule][$occurrence] ?? $this->patternsForEveryOccurrence[$rule] ?? null;
    }

    /**
     * Answers a plan that directs every further occurrence of one rule the same way.
     *
     * @param non-empty-string $rule Rule to direct
     * @param ProductionPattern $pattern Pattern every occurrence not named directly takes
     *
     * @return self<TRequiresNonEmpty> Plan carrying that fallback
     */
    public function withPatternForEveryOccurrence(string $rule, ProductionPattern $pattern): self
    {
        return new self(
            $this->startRule,
            $this->patterns,
            [...$this->patternsForEveryOccurrence, $rule => $pattern],
            $this->lexemes,
            $this->lexicalTarget,
            $this->parameters,
            $this->requiresNonEmpty,
            $this->maxDepth,
        );
    }

    /**
     * Answers the lexeme one occurrence of a terminal is spelled with.
     *
     * @param string $terminal Terminal the walk has reached
     * @param int $occurrence How many times the walk has reached it before
     *
     * @return non-empty-string|null Lexeme to write, or null when the walk may choose freely
     */
    public function lexemeAt(string $terminal, int $occurrence): ?string
    {
        return $this->lexemes[$terminal][$occurrence] ?? null;
    }

    /**
     * Answers the lexical rule to realize instead of walking the grammar.
     *
     * @return non-empty-string|null Lexical rule to realize, or null when the grammar is walked
     */
    public function lexicalTarget(): ?string
    {
        return $this->lexicalTarget;
    }

    /**
     * Answers the parameters the lexical target is realized with.
     *
     * @return array<string, int> Parameters by name
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Answers whether the walk must produce at least one symbol.
     *
     * @return TRequiresNonEmpty True when an empty result is refused
     */
    public function requiresNonEmpty(): bool
    {
        return $this->requiresNonEmpty;
    }

    /**
     * Answers how deep the walk may recurse.
     *
     * @return int Deepest recursion the walk may reach
     */
    public function maxDepth(): int
    {
        return $this->maxDepth;
    }
}
