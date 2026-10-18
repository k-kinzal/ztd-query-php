<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver\Evaluator\Builtin\FunctionEvaluator;
use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Call\CallDescription;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\Plan\PlanCompiler;
use Deriver\Deriver\Evaluator\Call\Plan\PlanValidation;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\Inputs;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Sources\SourceRef;

/**
 * Selects ordinary source bodies before optional replacement evaluators.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
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
     * Obtains a common IR body from source or a declarative evaluator.
     * @param string $symbol Call target
     * @param Instruction $instruction Invocation provenance
     * @param list<PassedArgument>|null $arguments Evaluated arguments, or null during preparation
     * @param State|null $state Calling state for immutable abstract input values
     * @param string $receiverType Known receiver class
     * @return CallableGraph|null Available semantics
     * @throws \Deriver\Exception\EvaluationContractException If the selected plan violates its declared contract
     */
    public function body(string $symbol, Instruction $instruction, ?array $arguments = null, ?State $state = null, string $receiverType = ''): ?CallableGraph
    {
        $source = $this->context->program->callable($symbol);
        $evaluator = $this->context->evaluators->evaluators[(new CallableIdentity())->key($symbol)] ?? null;
        $descriptor = $this->context->evaluators->descriptors[(new CallableIdentity())->key($symbol)] ?? null;
        if ($source !== null && !$source->external && !($descriptor->replaceSource ?? false)) {
            return $source;
        }
        if ($evaluator === null && $this->context->configuration->standardEvaluators) {
            $evaluator = (new Library())->evaluator($symbol);
        }
        if ($evaluator === null) {
            return $source;
        }
        $descriptor ??= $evaluator->descriptor();
        if ($descriptor->useSourceSignature && $source !== null) {
            $signature = (new \Deriver\Deriver\Evaluator\Declaration\Declarations($this->context->program))->signature($symbol) ?? $descriptor->signature;
            $descriptor = new CallDescriptor($descriptor->id, $descriptor->version, $descriptor->symbol, $signature, $descriptor->priority, $descriptor->replaces, $descriptor->replaceSource, true);
        }
        $decision = $evaluator->evaluate($this->description($descriptor, $symbol, $instruction, $arguments, $state, $receiverType));
        if ($decision->kind === \Deriver\Deriver\Evaluator\Call\DecisionKind::Declined) {
            return $source;
        }
        if ($decision->kind === \Deriver\Deriver\Evaluator\Call\DecisionKind::Unsupported || $decision->plan === null) {
            if ($decision->fallbackToSource && $source !== null) {
                return $source;
            }
            $reason = 'UNSUPPORTED_EVALUATOR_CASE';
            $this->context->callFailures[$instruction->id] = $reason;
            $this->context->frontier($reason, $instruction->source, $decision->reason);
            return null;
        }
        $violations = (new PlanValidation())->validate($decision->plan, $descriptor->signature, $this->context->evaluators->state->slots);
        if ($violations !== []) {
            throw new \Deriver\Exception\EvaluationContractException($descriptor->id . ': ' . implode(', ', $violations));
        }
        $this->context->assumptions[] = 'evaluator:' . $descriptor->id . '@' . $descriptor->version;
        $at = new SourceRef($instruction->source->snapshotId, 'evaluator:' . $descriptor->id . '@' . $descriptor->version, 0, 0);
        $body = (new PlanCompiler($at))->compile($descriptor, $decision->plan, $source);
        if ($evaluator instanceof FunctionEvaluator) {
            $this->context->nativeCalls[$body] = true;
        }
        return $body;
    }

    /**
     * Builds immutable evaluator inputs without exposing solver objects or evaluating application code.
     * @param CallDescriptor $descriptor Frozen evaluator contract
     * @param string $symbol Selected callable name
     * @param Instruction $instruction Invocation provenance
     * @param list<PassedArgument>|null $arguments Evaluated actuals, or null during preparation
     * @param State|null $state Current argument storage
     * @param string $receiverType Known dynamic receiver class
     * @return CallDescription Formal or evaluated signature-normalized metadata
     */
    public function description(CallDescriptor $descriptor, string $symbol, Instruction $instruction, ?array $arguments, ?State $state, string $receiverType = ''): CallDescription
    {
        $receiverType = $receiverType === '' && str_contains($symbol, '::') ? explode('::', $symbol, 2)[0] : $receiverType;
        $inputs = $arguments !== null && $state !== null ? (new Inputs())->bindings($descriptor, $instruction, $arguments, $state) : null;
        return new CallDescription($symbol, $descriptor->signature, $this->context->configuration->target, $receiverType, $inputs, $instruction->source, new \Deriver\Deriver\Evaluator\Declaration\Declarations($this->context->program));
    }

    /**
     * Resolves namespace fallback without invoking or compiling a function body.
     * @param string $symbol Requested function name
     * @param Instruction $instruction Call site with optional fallback spelling
     * @return string Selected source or evaluator name
     */
    public function name(string $symbol, Instruction $instruction): string
    {
        $fallback = $instruction->attributes['fallback'] ?? '';
        if ($this->context->program->callable($symbol) !== null || isset($this->context->evaluators->evaluators[(new CallableIdentity())->key($symbol)])) {
            return $symbol;
        }
        if (!is_string($fallback) || $fallback === '') {
            return $symbol;
        }
        $standard = $this->context->configuration->standardEvaluators ? (new Library())->evaluator($fallback) : null;
        return $this->context->program->callable($fallback) !== null || isset($this->context->evaluators->evaluators[(new CallableIdentity())->key($fallback)]) || $standard !== null ? $fallback : $symbol;
    }
}
