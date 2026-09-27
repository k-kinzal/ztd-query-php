<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\SemanticPlan;
use Override;

/**
 * Registers an independent declarative plan for model contract fixtures.
 * @visibility root
 */
final class PlanModel implements CallModel
{
    /**
     * @param ModelDescriptor $metadata Stable model registration
     * @param SemanticPlan $plan Ordered API semantics
     */
    public function __construct(public readonly ModelDescriptor $metadata, public readonly SemanticPlan $plan)
    {
    }

    /**
     * @return ModelDescriptor Stable registration
     */
    #[Override]
    public function descriptor(): ModelDescriptor
    {
        return $this->metadata;
    }

    /**
     * @param CallDescription $call Selected target
     * @return ModelDecision Declarative fixture semantics
     */
    #[Override]
    public function describe(CallDescription $call): ModelDecision
    {
        return ModelDecision::handled($this->plan);
    }
}
