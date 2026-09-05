<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Reports that a grammar could not derive a statement.
 *
 * A rule with no alternatives, an alternative no lexer can realize, a reference
 * to a rule the grammar does not define, or a derivation that runs past its
 * depth limit are all properties of the grammar being generated from, not of
 * the generator. Callers that generate from grammars they did not author are
 * expected to handle this.
 *
 * The three dialect generators hit the same failures, so the wording lives here
 * rather than being repeated at each throw site.
 */
final class GenerationException extends RuntimeException
{
    /**
     * Reports a derivation that ran past the plan's depth budget.
     *
     * @return self Exception describing the exhausted budget
     */
    public static function derivationLimitExceeded(): self
    {
        return new self('Exceeded derivation limit while generating SQL.');
    }

    /**
     * Reports a reference to a rule the grammar does not define.
     *
     * @param string $rule Name the production referred to
     *
     * @return self Exception naming the missing rule
     */
    public static function unknownRule(string $rule): self
    {
        return new self("Unknown grammar rule: {$rule}");
    }

    /**
     * Reports a rule that declares no alternatives to choose from.
     *
     * @param string $rule Name of the empty rule
     *
     * @return self Exception naming the empty rule
     */
    public static function ruleHasNoAlternatives(string $rule): self
    {
        return new self("Production rule '{$rule}' has no alternatives.");
    }

    /**
     * Reports a rule whose alternatives cannot all be turned into tokens.
     *
     * @param string $rule Name of the unrealizable rule
     *
     * @return self Exception naming the unrealizable rule
     */
    public static function noRealizableAlternative(string $rule): self
    {
        return new self("Grammar rule has no lexically realizable alternative: {$rule}");
    }

    /**
     * Reports a rule with no alternative the generation plan admits.
     *
     * @param string $rule Name of the constrained rule
     *
     * @return self Exception naming the constrained rule
     */
    public static function noAlternativeMatchingPlan(string $rule): self
    {
        return new self("Grammar rule has no alternative matching the generation plan: {$rule}");
    }

    /**
     * Reports a start rule that cannot satisfy the plan's non-empty requirement.
     *
     * @param string $rule Name of the start rule
     *
     * @return self Exception naming the start rule
     */
    public static function startRuleCannotProduceOutput(string $rule): self
    {
        return new self(
            "Generation plan requires non-empty output, but the start rule cannot produce it: {$rule}",
        );
    }

    /**
     * Reports a plan that demanded output the grammar only produced as empty.
     *
     * @param string $dialect Dialect the generator was producing for
     *
     * @return self Exception naming the dialect
     */
    public static function planRequiresNonEmptyOutput(string $dialect): self
    {
        return new self("{$dialect} generation plan requires non-empty output.");
    }

    /**
     * Reports that every derivation attempt failed to reach concrete SQL.
     *
     * @param string $dialect Dialect the generator was producing for
     *
     * @return self Exception naming the dialect
     */
    public static function lexicalRealizationFailed(string $dialect): self
    {
        return new self("{$dialect} lexical realization failed.");
    }
}
