<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

use SqlFaker\Grammar\Model\ProductionPattern;
use SqlFaker\Grammar\Walk\GenerationException;

/**
 * Answers which of a rule's alternatives a walk may still take.
 *
 * Three things narrow the choice, and each of them can narrow it to nothing:
 * an alternative that cannot terminate is never viable, a plan may name the
 * shape it wants at this point in the walk, and the first step of a walk that
 * was asked for output cannot take an alternative that produces none. Each
 * refusal names the rule it happened at, because "no alternative" on its own
 * does not say which of the three did the narrowing.
 */
final class ViableAlternatives
{
    /**
     * Binds the narrowing to what decides whether a production terminates.
     *
     * @param TerminationAnalyzer $analyzer Answers whether a production can terminate and how much it produces
     */
    public function __construct(private readonly TerminationAnalyzer $analyzer)
    {
    }

    /**
     * Answers the alternatives still open for one non-terminal.
     *
     * @param ProductionRule $rule Rule the walk arrived at
     * @param ProductionPattern|null $pattern Shape the plan asks for here, or null when the walk may choose freely
     * @param bool $mustProduceOutput Whether an alternative that produces nothing is acceptable
     *
     * @return non-empty-list<Production> The alternatives that remain
     *
     * @throws GenerationException When the rule has no alternatives, or the narrowing leaves none
     */
    public function of(ProductionRule $rule, ?ProductionPattern $pattern, bool $mustProduceOutput): array
    {
        if ($rule->alternatives === []) {
            throw GenerationException::ruleHasNoAlternatives($rule->lhs);
        }

        $alternatives = array_values(array_filter(
            $rule->alternatives,
            $this->analyzer->isProductionViable(...),
        ));
        if ($alternatives === []) {
            throw GenerationException::noRealizableAlternative($rule->lhs);
        }

        if ($pattern !== null) {
            $alternatives = array_values(array_filter(
                $alternatives,
                static fn (Production $production): bool => $pattern->matches(array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $production->symbols,
                )),
            ));
            if ($alternatives === []) {
                throw GenerationException::noAlternativeMatchingPlan($rule->lhs);
            }
        }

        if ($mustProduceOutput) {
            $alternatives = array_values(array_filter(
                $alternatives,
                fn (Production $production): bool => $this->analyzer->estimateProductionLength($production) > 0,
            ));
            if ($alternatives === []) {
                throw GenerationException::startRuleCannotProduceOutput($rule->lhs);
            }
        }

        return $alternatives;
    }
}
