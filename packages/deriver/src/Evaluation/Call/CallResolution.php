<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Model\Inputs;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Model\Builtin\FunctionModel;
use Deriver\Model\Builtin\Library;
use Deriver\Model\CallDescription;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Compilation\PlanValidation;
use Deriver\Model\ModelDescriptor;
use Deriver\Reference\SourceRef;

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
     * @param State|null $state Calling state for immutable abstract input values
     * @param string $receiverType Known receiver class
     * @return CallableGraph|null Available semantics
     * @throws \Deriver\Exception\ModelContractException If the selected plan violates its declared contract
     */
    public function body(string $symbol, Instruction $instruction, ?array $arguments = null, ?State $state = null, string $receiverType = ''): ?CallableGraph
    {
        $source = $this->context->program->callable($symbol);
        $model = $this->context->models->models[(new CallableIdentity())->key($symbol)] ?? null;
        $descriptor = $this->context->models->descriptors[(new CallableIdentity())->key($symbol)] ?? null;
        if ($source !== null && !$source->external && !($descriptor->replaceSource ?? false)) {
            return $source;
        }
        if ($model === null && $this->context->configuration->standardModels) {
            $model = (new Library())->model($symbol);
        }
        if ($model === null) {
            return $source;
        }
        $descriptor ??= $model->descriptor();
        $decision = $model->describe($this->description($descriptor, $symbol, $instruction, $arguments, $state, $receiverType));
        if ($decision->kind === 'declined') {
            return $source;
        }
        if ($decision->kind === 'unsupported' || $decision->plan === null) {
            if ($decision->fallbackToSource && $source !== null) {
                return $source;
            }
            $reason = 'UNSUPPORTED_MODEL_CASE';
            $this->context->callFailures[$instruction->id] = $reason;
            $this->context->frontier($reason, $instruction->source, $decision->reason);
            return null;
        }
        $violations = (new PlanValidation())->validate($decision->plan, $descriptor->signature, $this->context->models->state->slots);
        if ($violations !== []) {
            throw new \Deriver\Exception\ModelContractException($descriptor->id . ': ' . implode(', ', $violations));
        }
        $this->context->assumptions[] = 'model:' . $descriptor->id . '@' . $descriptor->version;
        $at = new SourceRef($instruction->source->snapshotId, 'model:' . $descriptor->id . '@' . $descriptor->version, 0, 0);
        $body = (new PlanCompiler($at))->compile($descriptor, $decision->plan, $source);
        if ($model instanceof FunctionModel) {
            $this->context->nativeCalls[$body] = true;
        }
        return $body;
    }

    /**
     * Builds immutable model inputs without exposing solver objects or evaluating application code.
     * @param ModelDescriptor $descriptor Frozen model contract
     * @param string $symbol Selected callable name
     * @param Instruction $instruction Invocation provenance
     * @param list<PassedArgument>|null $arguments Evaluated actuals, or null during preparation
     * @param State|null $state Current argument storage
     * @param string $receiverType Known dynamic receiver class
     * @return CallDescription Formal or evaluated signature-normalized metadata
     */
    public function description(ModelDescriptor $descriptor, string $symbol, Instruction $instruction, ?array $arguments, ?State $state, string $receiverType = ''): CallDescription
    {
        $receiverType = $receiverType === '' && str_contains($symbol, '::') ? explode('::', $symbol, 2)[0] : $receiverType;
        $inputs = $arguments !== null && $state !== null ? (new Inputs())->bindings($descriptor, $instruction, $arguments, $state) : null;
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
        if ($this->context->program->callable($symbol) !== null || isset($this->context->models->models[(new CallableIdentity())->key($symbol)])) {
            return $symbol;
        }
        if (!is_string($fallback) || $fallback === '') {
            return $symbol;
        }
        $standard = $this->context->configuration->standardModels ? (new Library())->model($fallback) : null;
        return $this->context->program->callable($fallback) !== null || isset($this->context->models->models[(new CallableIdentity())->key($fallback)]) || $standard !== null ? $fallback : $symbol;
    }
}
