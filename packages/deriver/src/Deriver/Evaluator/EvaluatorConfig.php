<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

use Deriver\Exception\InvalidInputException;

/**
 * Semantic inputs for dependency derivation.
 * @visibility root
 */
final class EvaluatorConfig
{
    /**
     * @var list<Micro\ConfiguredEvaluator> Micro evaluators ordered by priority
     */
    public readonly array $evaluators;

    /**
     * Whether a configured evaluator can request predicate dependencies.
     */
    public readonly bool $hasBranchEvaluators;

    /**
     * @param array<string, Term> $environment Supplied symbolic inputs
     * @param list<Micro\ConfiguredEvaluator> $evaluators Converted application micro evaluators
     * @throws InvalidInputException If evaluator identities or selectors conflict
     */
    public function __construct(
        public readonly TargetProfile $target = new TargetProfile(),
        public readonly array $environment = [],
        public readonly bool $standardEvaluators = true,
        array $evaluators = [],
    ) {
        $selectors = [];
        $identities = [];
        foreach ($evaluators as $evaluator) {
            $key = serialize([$evaluator->operation, $evaluator->name, $evaluator->priority, $evaluator->path, $evaluator->start, $evaluator->end]);
            if (isset($selectors[$key]) || isset($identities[$evaluator->id])) {
                throw new InvalidInputException('EVALUATOR_CONFLICT: micro evaluators repeat an identity or share a selector and priority.');
            }
            $selectors[$key] = true;
            $identities[$evaluator->id] = true;
        }
        usort($evaluators, static fn ($left, $right): int => $right->priority <=> $left->priority);
        $this->evaluators = $evaluators;
        $this->hasBranchEvaluators = count(array_filter($evaluators, static fn (Micro\ConfiguredEvaluator $evaluator): bool => $evaluator->operation === 'branch')) > 0;
    }
}
