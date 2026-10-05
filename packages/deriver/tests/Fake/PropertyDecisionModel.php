<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\CallDescription;
use Deriver\Model\DemandModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Override;

/**
 * A replacement whose selection depends on a caller-derived input.
 * @visibility root
 */
final class PropertyDecisionModel implements DemandModel
{
    /**
     * Selects the fixture's constructor or ordinary method.
     */
    public function __construct(private readonly string $symbol)
    {
    }

    /**
     * Describes the model's selection input.
     */
    #[Override]
    public function descriptor(): ModelDescriptor
    {
        return new ModelDescriptor('property-decision', '1', $this->symbol, new Signature([new Parameter('mode')]));
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function demand(CallDescription $call): array
    {
        return ['mode'];
    }

    /**
     * Separates replacement, explicit source fallback and unresolved model behavior.
     */
    #[Override]
    public function describe(CallDescription $call): ModelDecision
    {
        return match ($call->arguments->arguments['mode']->value->literal) {
            'replace' => ModelDecision::handled(new SemanticPlan([])),
            'source' => ModelDecision::declined(),
            default => ModelDecision::unsupported('Unknown mode', false),
        };
    }
}
