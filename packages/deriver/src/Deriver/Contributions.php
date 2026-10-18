<?php

declare(strict_types=1);

namespace Deriver\Deriver;

use Deriver\Deriver\Evaluator\Call\Provider\Provider;
use Deriver\Deriver\Evaluator\Call\State\StateSlot;
use Deriver\Deriver\Evaluator\Domain\AbstractDomain;
use Deriver\Deriver\Evaluator\Intrinsic\PureIntrinsic;

/**
 * Internal extension inputs assembled by Deriver, independently of configuration settings.
 * @visibility root
 */
final class Contributions
{
    /**
     * @param list<Evaluator\Call\CallEvaluator> $evaluators Trusted call evaluators
     * @param list<Provider> $providers Captured analysis and dispatch contracts
     * @param list<AbstractDomain> $domains Registered abstract lattices
     * @param list<PureIntrinsic> $intrinsics Pure domain-specific operations
     * @param list<StateSlot> $stateSlots Abstract state initialization and lifecycle contracts
     * @param array<string, Evaluator\Term> $environment Symbolic input values
     */
    public function __construct(
        public readonly array $evaluators = [],
        public readonly array $providers = [],
        public readonly array $domains = [],
        public readonly array $intrinsics = [],
        public readonly array $stateSlots = [],
        public readonly array $environment = [],
    ) {
    }
}
