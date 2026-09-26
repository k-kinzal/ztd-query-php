<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Call;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Model\PlanCompiler;
use Deriver\Internal\Solver\Context;
use Deriver\Model\CallDescription;
use Deriver\Standard\Library;

/**
 * Selects ordinary source bodies before optional replacement models.
 * @visibility root
 */
final class CallResolution
{
    /**
     * @param Context $context Query world
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Obtains a common IR body from source or a declarative model.
     * @param string $symbol Call target
     * @param Instruction $instruction Invocation provenance
     * @param list<PassedArgument>|null $arguments Evaluated arguments, or null during preparation
     * @param \Deriver\Internal\Solver\State|null $state Calling state for immutable abstract input values
     * @param string $receiverType Known receiver class
     * @return CallableIR|null Available semantics
     */
    public function body(string $symbol, Instruction $instruction, ?array $arguments = null, ?\Deriver\Internal\Solver\State $state = null, string $receiverType = ''): ?CallableIR
    {
        $source = $this->context->program->callable($symbol);
        $model = $this->context->models->models[strtolower($symbol)] ?? null;
        $descriptor = $this->context->models->descriptors[strtolower($symbol)] ?? null;
        if ($source !== null && !$source->external && !($descriptor->replaceSource ?? false)) {
            return $source;
        }
        if ($model === null && $this->context->configuration->standardModels) {
            $model = (new Library())->model($symbol);
        }
        if ($model === null) {
            return $source;
        }
        $descriptor ??= (new \Deriver\Internal\Model\ModelBoundary())->descriptor($model);
        $decision = (new \Deriver\Internal\Model\ModelBoundary())->describe($model, $this->description($descriptor, $symbol, $instruction, $arguments, $state, $receiverType));
        if ($decision->kind === 'declined') {
            return $source;
        }
        if ($decision->kind === 'unsupported' || $decision->plan === null) {
            if ($decision->fallbackToSource && $source !== null) {
                return $source;
            }
            $reason = str_starts_with($decision->reason, 'MODEL_CONTRACT_VIOLATION:') ? 'MODEL_CONTRACT_VIOLATION' : 'UNSUPPORTED_MODEL_CASE';
            $this->context->callFailures[$instruction->id] = $reason;
            $this->context->frontier($reason, $instruction->source, $decision->reason);
            return null;
        }
        $violations = (new \Deriver\Internal\Model\PlanValidation())->validate($decision->plan, $descriptor->signature, $this->context->models->state);
        if ($violations !== []) {
            $this->context->callFailures[$instruction->id] = 'MODEL_CONTRACT_VIOLATION';
            $this->context->frontier('MODEL_CONTRACT_VIOLATION', $instruction->source, implode(',', $violations));
            return null;
        }
        $this->context->assumptions[] = 'model:' . $descriptor->id . '@' . $descriptor->version;
        $at = new SourceRef($instruction->source->snapshotId, 'model:' . $descriptor->id . '@' . $descriptor->version, 0, 0);
        $body = (new PlanCompiler($at))->compile($descriptor, $decision->plan, $source);
        if ($model instanceof \Deriver\Standard\FunctionModel) {
            $this->context->nativeCalls[$body] = true;
        }
        return $body;
    }

    /**
     * Builds immutable model inputs without exposing solver objects or evaluating application code.
     * @param \Deriver\Model\ModelDescriptor $descriptor Frozen model contract
     * @param string $symbol Selected callable name
     * @param Instruction $instruction Invocation provenance
     * @param list<PassedArgument>|null $arguments Evaluated actuals, or null during preparation
     * @param \Deriver\Internal\Solver\State|null $state Current argument storage
     * @param string $receiverType Known dynamic receiver class
     * @return CallDescription Formal or evaluated signature-normalized metadata
     */
    public function description(\Deriver\Model\ModelDescriptor $descriptor, string $symbol, Instruction $instruction, ?array $arguments, ?\Deriver\Internal\Solver\State $state, string $receiverType = ''): CallDescription
    {
        $receiverType = $receiverType === '' && str_contains($symbol, '::') ? explode('::', $symbol, 2)[0] : $receiverType;
        $inputs = $arguments !== null && $state !== null ? (new Model\Inputs())->bindings($descriptor, $instruction, $arguments, $state) : null;
        return new CallDescription($symbol, $descriptor->signature, $this->context->configuration->target, $receiverType, $inputs, $this->context->configuration->dependencyVersions);
    }

    /**
     * Resolves namespace fallback without invoking or compiling a function body.
     * @param string $symbol Requested function name
     * @param Instruction $instruction Call site with optional fallback spelling
     * @return string Selected source or model name
     */
    public function name(string $symbol, Instruction $instruction): string
    {
        $fallback = $instruction->attributes['fallback'] ?? '';
        if ($this->context->program->callable($symbol) !== null || isset($this->context->models->models[strtolower($symbol)])) {
            return $symbol;
        }
        if (!is_string($fallback) || $fallback === '') {
            return $symbol;
        }
        $standard = $this->context->configuration->standardModels ? (new Library())->model($fallback) : null;
        return $this->context->program->callable($fallback) !== null || isset($this->context->models->models[strtolower($fallback)]) || $standard !== null ? $fallback : $symbol;
    }
}
