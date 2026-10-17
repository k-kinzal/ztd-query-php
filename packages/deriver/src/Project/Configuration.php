<?php

declare(strict_types=1);

namespace Deriver\Project;

use Deriver\Value\Term;

/**
 * Explicit semantic assumptions for one immutable snapshot: the target, the environment, the limits and the replacement rules.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Project\Configuration())->closedWorld // => false
 */
final class Configuration
{
    /**
     * @param TargetProfile $target target
     * @param bool $closedWorld closedWorld
     * @param array<string, Term> $environment environment
     * @param string $environmentVersion environmentVersion
     * @param bool $standardModels standardModels
     * @param ResourceLimits $resources Runtime interruption policy
     * @param array<string, string> $dependencyVersions Captured dependency package versions used by model contracts
     * @param SourceLimits $sourceLimits Admission policy before source parsing and indexing
     * @param int $candidateCacheEntries Maximum retained dependency evaluations; zero disables retention
     * @param list<Expansion\Rule> $expansionRules User rules selected before operands
     * @param int $retainedResults Maximum recent small result graphs owned by the session; zero disables retention
     * @throws \Deriver\Exception\InvalidInputException If the contract or retention capacity is invalid
     */
    public function __construct(
        public readonly TargetProfile $target = new TargetProfile(),
        public readonly bool $closedWorld = false,
        public readonly array $environment = [],
        public readonly string $environmentVersion = 'unspecified',
        public readonly bool $standardModels = true,
        public readonly ResourceLimits $resources = new ResourceLimits(),
        public readonly array $dependencyVersions = [],
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
