<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver\Evaluator\Call\Provider\DispatchDecision;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchProvider;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchRequest;
use Deriver\Deriver\Evaluator\Execution\Context;

/**
 * Combines provider candidates while keeping completeness and provenance explicit.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class ProviderDispatch
{
    /**
     * @param Context $context Query world and diagnostics
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Collects deterministic target alternatives from registered contracts.
     * @param DispatchRequest $request Evaluated call metadata
     * @return DispatchDecision Combined candidates and completeness
     */
    public function resolve(DispatchRequest $request): DispatchDecision
    {
        $targets = [];
        $exhaustive = false;
        $assumptions = [];
        foreach ($this->context->evaluators->contributions->providers as $provider) {
            if (!$provider instanceof DispatchProvider) {
                continue;
            }
            $decision = $provider->resolve($request);
            array_push($targets, ...$decision->targets);
            array_push($assumptions, ...$decision->assumptions);
            $exhaustive = $exhaustive || $decision->exhaustive;
        }
        array_push($this->context->assumptions, ...$assumptions);
        return new DispatchDecision($targets, $exhaustive, $assumptions);
    }
}
