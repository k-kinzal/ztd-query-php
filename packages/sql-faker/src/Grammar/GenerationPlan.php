<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use InvalidArgumentException;

/** @template-covariant TRequiresNonEmpty of bool */
final class GenerationPlan
{
    /**
     * @param array<string, non-empty-list<ProductionPattern>> $patterns
     * @param array<string, ProductionPattern> $patternsForEveryOccurrence
     * @param array<string, non-empty-list<non-empty-string>> $lexemes
     * @param array<string, int> $parameters
     * @param TRequiresNonEmpty $requiresNonEmpty
     */
    private function __construct(
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

    /** @return self<false> */
    public static function all(): self
    {
        return new self(null, [], [], [], null, [], false, PHP_INT_MAX);
    }

    /** @return self<false> */
    public static function fromRule(string $startRule): self
    {
        self::assertStartRule($startRule);

        return new self($startRule, [], [], [], null, [], false, PHP_INT_MAX);
    }

    /**
     * @param array<string, non-empty-list<ProductionPattern>> $patterns
     * @return self<false>
     */
    public static function constrained(string $startRule, array $patterns): self
    {
        self::assertStartRule($startRule);
        if ($patterns === []) {
            throw new InvalidArgumentException('A constrained generation plan requires production patterns.');
        }

        return new self($startRule, $patterns, [], [], null, [], false, PHP_INT_MAX);
    }

    /**
     * @param array<string, int> $parameters
     * @return self<true>
     */
    public static function lexical(string $target, array $parameters): self
    {
        if ($target === '') {
            throw new InvalidArgumentException('A lexical generation target must not be empty.');
        }

        return new self(null, [], [], [], $target, $parameters, true, PHP_INT_MAX);
    }

    /** @return self<true> */
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
     * @param array<string, non-empty-list<non-empty-string>> $lexemes
     * @return self<TRequiresNonEmpty>
     */
    public function withLexemes(array $lexemes): self
    {
        if ($lexemes === []) {
            throw new InvalidArgumentException('A lexical generation plan requires lexemes.');
        }

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

    /** @return self<TRequiresNonEmpty> */
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

    public function startRule(): ?string
    {
        return $this->startRule;
    }

    public function patternAt(string $rule, int $occurrence): ?ProductionPattern
    {
        return $this->patterns[$rule][$occurrence] ?? $this->patternsForEveryOccurrence[$rule] ?? null;
    }

    /** @return self<TRequiresNonEmpty> */
    public function withPatternForEveryOccurrence(string $rule, ProductionPattern $pattern): self
    {
        if ($rule === '') {
            throw new InvalidArgumentException('A generation plan rule must not be empty.');
        }

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

    /** @return non-empty-string|null */
    public function lexemeAt(string $terminal, int $occurrence): ?string
    {
        return $this->lexemes[$terminal][$occurrence] ?? null;
    }

    public function lexicalTarget(): ?string
    {
        return $this->lexicalTarget;
    }

    /** @return array<string, int> */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /** @return TRequiresNonEmpty */
    public function requiresNonEmpty(): bool
    {
        return $this->requiresNonEmpty;
    }

    public function maxDepth(): int
    {
        return $this->maxDepth;
    }

    private static function assertStartRule(string $startRule): void
    {
        if ($startRule === '') {
            throw new InvalidArgumentException('A generation plan start rule must not be empty.');
        }
    }
}
