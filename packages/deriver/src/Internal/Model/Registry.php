<?php

declare(strict_types=1);

namespace Deriver\Internal\Model;

use Deriver\Api\InvalidInputException;
use Deriver\Api\Project\Configuration;
use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;

/**
 * Resolves explicit model precedence independently of registration order.
 * @visibility root
 */
final class Registry
{
    /**
     * @var array<string, CallModel> Selected models by case-insensitive symbol.
     */
    public array $models = [];
    /**
     * @var array<string, ModelDescriptor> Frozen selected descriptors by normalized symbol.
     */
    public array $descriptors = [];
    /**
     * @var array<string, ModelDescriptor> Stable model manifest by identifier.
     */
    public array $manifest = [];
    /**
     * Validated domain and intrinsic registrations.
     */
    public readonly Extensions $extensions;

    /**
     * Registered abstract object slots.
     */
    public readonly StateRegistry $state;

    /**
     * @param Configuration $configuration Trusted explicit model configuration
     * @throws InvalidInputException If matching models conflict or identifiers repeat
     */
    public function __construct(public readonly Configuration $configuration)
    {
        $this->extensions = new Extensions($configuration);
        $this->state = new StateRegistry($configuration->stateSlots);
        $candidates = [];
        foreach ($configuration->models as $model) {
            $descriptor = (new ModelBoundary())->descriptor($model);
            $this->register($descriptor);
            $candidates[strtolower(ltrim($descriptor->symbol, '\\'))][$descriptor->id] = $model;
        }
        foreach ($candidates as $symbol => $models) {
            $descriptors = array_intersect_key($this->manifest, $models);
            $winner = (new ModelPrecedence())->select($descriptors);
            $this->models[$symbol] = $models[$winner];
            $this->descriptors[$symbol] = $this->manifest[$winner];
        }
        ksort($this->manifest);
        ksort($this->models);
    }

    /**
     * Captures and validates one registration without selecting by input order.
     * @param ModelDescriptor $descriptor Immutable model metadata
     * @throws InvalidInputException If identity or standard replacement is invalid
     */
    public function register(ModelDescriptor $descriptor): void
    {
        if (isset($this->manifest[$descriptor->id])) {
            throw new InvalidInputException('MODEL_CONFLICT: repeated model ID ' . $descriptor->id);
        }
        $symbol = strtolower(ltrim($descriptor->symbol, '\\'));
        if ($descriptor->id === '' || $descriptor->version === '' || $symbol === '') {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: model identity fields cannot be empty.');
        }
        $standard = $this->configuration->standardModels ? (new \Deriver\Standard\Library())->model($symbol) : null;
        if ($standard !== null && !in_array($standard->descriptor()->id, $descriptor->replaces, true)) {
            throw new InvalidInputException('MODEL_CONFLICT: replacing a standard model requires an explicit replaces declaration.');
        }
        $this->manifest[$descriptor->id] = $descriptor;
    }

    /**
     * Selects by replacement intent and declared priority, rejecting equal rank.
     * @param ModelDescriptor $candidate Candidate metadata
     * @param ModelDescriptor $previous Previously selected metadata
     * @return bool Whether the candidate wins
     * @throws InvalidInputException If neither model has unambiguous precedence
     */
    public function preferred(ModelDescriptor $candidate, ModelDescriptor $previous): bool
    {
        if (in_array($previous->id, $candidate->replaces, true)) {
            return true;
        }
        if (in_array($candidate->id, $previous->replaces, true)) {
            return false;
        }
        if ($candidate->priority === $previous->priority) {
            throw new InvalidInputException('MODEL_CONFLICT: ' . $candidate->symbol);
        }
        return $candidate->priority > $previous->priority;
    }

    /**
     * Returns the selected model's declarative decision.
     * @param string $symbol Normalized call target
     * @param string $receiverType Receiver type
     * @return ModelDecision Selected semantics
     */
    public function describe(string $symbol, string $receiverType = ''): ModelDecision
    {
        $model = $this->models[strtolower($symbol)] ?? null;
        if ($model === null) {
            return ModelDecision::declined();
        }
        $descriptor = $this->descriptors[strtolower($symbol)];
        return (new ModelBoundary())->describe($model, new CallDescription($symbol, $descriptor->signature, $this->configuration->target, $receiverType, dependencyVersions: $this->configuration->dependencyVersions));
    }
}
