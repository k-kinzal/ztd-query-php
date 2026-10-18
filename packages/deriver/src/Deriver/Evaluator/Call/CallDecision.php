<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;

/**
 * Distinguishes a handled call, a declined matcher, and an unsupported overload.
 *
 * @visibility root
 * @example Declining an unrelated call
 *     \Deriver\Deriver\Evaluator\Call\CallDecision::declined()->kind === \Deriver\Deriver\Evaluator\Call\DecisionKind::Declined // => true
 */
final class CallDecision
{
    /**
     * @param DecisionKind $kind Decision kind
     * @param SemanticPlan|null $plan Declarative semantics
     * @param string $reason Unsupported-case explanation
     * @param bool $fallbackToSource Whether a known source body may be used
     */
    private function __construct(public readonly DecisionKind $kind, public readonly ?SemanticPlan $plan = null, public readonly string $reason = '', public readonly bool $fallbackToSource = false)
    {
    }

    /**
     * Supplies the complete ordered semantics for a matched call.
     * @param SemanticPlan $plan Declarative program
     * @return self Handled decision
     */
    public static function handled(SemanticPlan $plan): self
    {
        return new self(DecisionKind::Handled, $plan);
    }

    /**
     * Leaves the call to another applicable evaluator or source analysis.
     * @return self Declined decision
     */
    public static function declined(): self
    {
        return new self(DecisionKind::Declined);
    }

    /**
     * Records a recognized but unsupported call form.
     * @param string $reason Missing semantic capability
     * @param bool $fallbackToSource Whether a source implementation can be analyzed
     * @return self Unsupported decision
     */
    public static function unsupported(string $reason, bool $fallbackToSource = true): self
    {
        return new self(DecisionKind::Unsupported, reason: $reason, fallbackToSource: $fallbackToSource);
    }
}
