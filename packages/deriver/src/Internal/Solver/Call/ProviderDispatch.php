<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Call;

use Deriver\Internal\Model\ModelBoundary;
use Deriver\Internal\Solver\Context;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchProvider;
use Deriver\Model\Provider\DispatchRequest;

/**
 * Combines provider candidates while keeping completeness and provenance explicit.
 * @visibility root
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
        $failed = false;
        $assumptions = [];
        foreach ($this->context->configuration->providers as $provider) {
            if (!$provider instanceof DispatchProvider) {
                continue;
            }
            $decision = (new ModelBoundary())->dispatch($provider, $request);
            if ($decision === null) {
                $failed = true;
                $this->context->frontier('MODEL_CONTRACT_VIOLATION', $request->source, 'dispatch-provider');
                continue;
            }
            array_push($targets, ...$decision->targets);
            array_push($assumptions, ...$decision->assumptions);
            $exhaustive = $exhaustive || $decision->exhaustive;
        }
        array_push($this->context->assumptions, ...$assumptions);
        return new DispatchDecision($targets, $exhaustive && !$failed, $assumptions);
    }
}
