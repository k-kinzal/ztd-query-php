<?php

declare(strict_types=1);

namespace Deriver\Model;

use Deriver\Model\Plan\SemanticPlan;

/**
 * Distinguishes a handled call, a declined matcher, and an unsupported overload.
 *
 * @visibility public
 * @example Declining an unrelated call
 *     \Deriver\Model\ModelDecision::declined()->kind // => 'declined'
 */
final class ModelDecision
{
    /**
     * @param string $kind Decision kind
     * @param SemanticPlan|null $plan Declarative semantics
     * @param string $reason Unsupported-case explanation
     * @param bool $fallbackToSource Whether a known source body may be used
     */
    private function __construct(public readonly string $kind, public readonly ?SemanticPlan $plan = null, public readonly string $reason = '', public readonly bool $fallbackToSource = false)
    {
    }

    /**
     * Supplies the complete ordered semantics for a matched call.
     * @param SemanticPlan $plan Declarative program
     * @return self Handled decision
     */
    public static function handled(SemanticPlan $plan): self
    {
        return new self('handled', $plan);
    }

    /**
     * Leaves the call to another applicable model or source analysis.
     * @return self Declined decision
     */
    public static function declined(): self
    {
        return new self('declined');
    }

    /**
     * Records a recognized but unsupported call form.
     * @param string $reason Missing semantic capability
     * @param bool $fallbackToSource Whether a source implementation can be analyzed
     * @return self Unsupported decision
     */
    public static function unsupported(string $reason, bool $fallbackToSource = true): self
    {
        return new self('unsupported', reason: $reason, fallbackToSource: $fallbackToSource);
    }
}
