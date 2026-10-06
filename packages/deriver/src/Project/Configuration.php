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
     * @param int $candidateCacheEntries Maximum retained dependency evaluations; zero disables retention
     * @param list<\Deriver\Model\Expansion\Rule> $expansionRules User rules selected before operands
     * @param int $retainedResults Maximum recent small result graphs owned by the session; zero disables retention
     * @throws \Deriver\Exception\InvalidInputException If the contract or retention capacity is invalid
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
        public readonly int $candidateCacheEntries = 2048,
        public readonly int $retainedResults = 32,
        public readonly array $expansionRules = [],
    ) {
        if ($candidateCacheEntries < 0 || $retainedResults < 0) {
            throw new \Deriver\Exception\InvalidInputException('Retention limits must be nonnegative.');
        }
        $selectors = [];
        $identities = [];
        foreach ($expansionRules as $rule) {
            $key = serialize([$rule->operation, $rule->name, $rule->priority, $rule->path, $rule->start, $rule->end]);
            if (isset($selectors[$key]) || isset($identities[$rule->id])) {
                throw new \Deriver\Exception\InvalidInputException('MODEL_CONFLICT: expansion rules share a selector and priority.');
            }
            $selectors[$key] = true;
            $identities[$rule->id] = true;
        }
    }

}
