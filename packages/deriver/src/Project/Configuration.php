<?php

declare(strict_types=1);

namespace Deriver\Project;

use Deriver\Model\CallModel;
use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Query\ResourceLimits;
use Deriver\Value\Term;

/**
 * Explicit semantic assumptions and trusted models for one immutable snapshot.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Project\Configuration())->closedWorld // => false
 */
final class Configuration
{
    /**
     * @param TargetProfile $target target
     * @param list<CallModel> $models models
     * @param bool $closedWorld closedWorld
     * @param array<string, Term> $environment environment
     * @param string $environmentVersion environmentVersion
     * @param list<PureIntrinsic> $intrinsics Pure domain-specific operations
     * @param list<AbstractDomain> $domains Registered abstract lattices
     * @param list<\Deriver\Model\Provider\Provider> $providers Captured project and dispatch contracts
     * @param bool $standardModels standardModels
     * @param ResourceLimits $resources Runtime interruption policy
     * @param array<string, string> $dependencyVersions Captured dependency package versions used by model contracts
     * @param SourceLimits $sourceLimits Admission policy before source parsing and indexing
     * @param list<\Deriver\Model\State\StateSlot> $stateSlots Abstract state initialization and lifecycle contracts
     */
    public function __construct(
        public readonly TargetProfile $target = new TargetProfile(),
        public readonly array $models = [],
        public readonly bool $closedWorld = false,
        public readonly array $environment = [],
        public readonly string $environmentVersion = 'unspecified',
        public readonly bool $standardModels = true,
        public readonly array $intrinsics = [],
        public readonly array $domains = [],
        public readonly array $providers = [],
        public readonly ResourceLimits $resources = new ResourceLimits(),
        public readonly array $dependencyVersions = [],
        public readonly array $stateSlots = [],
        public readonly SourceLimits $sourceLimits = new SourceLimits(),
    ) {
    }
}
