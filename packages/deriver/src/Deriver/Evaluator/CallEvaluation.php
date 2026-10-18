<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Call\ArgumentBindings;
use Deriver\Deriver\Evaluator\Call\ArgumentDemand;
use Deriver\Deriver\Evaluator\Call\BoundArgument;
use Deriver\Deriver\Evaluator\Call\CallDescription;
use Deriver\Deriver\Evaluator\Call\Plan\PlanCompiler;
use Deriver\Deriver\Evaluator\Call\Plan\PlanValidation;
use Deriver\Deriver\Evaluator\Declaration\Declarations;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Sources\SourceRef;
use Override;
use Throwable;

/**
 * Selects a replacement before demanding any source body or irrelevant input.
 * @implements Micro\MicroEvaluator<BodyIndex|Term|null>
 * @visibility namespace
 */
final class CallEvaluation implements Micro\MicroEvaluator
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Evaluator $engine)
    {
    }

    /**
     * Evaluates a resolved call through the common micro evaluator contract.
     * @throws \Deriver\Exception\EvaluationContractException If an evaluator violates its contract
     */
    #[Override]
    public function evaluate(Micro\Request $request): BodyIndex|Term|null
    {
        $caller = $request->frame;
        $call = $request->instruction();
        $target = $request->function ?? $call->name;
        $depth = $request->depth;
        $context = $this->engine->context;
        $key = (new CallableIdentity())->key($target);
        $evaluator = $context->evaluators->evaluators[$key] ?? ($context->configuration->standardEvaluators ? (new Library())->evaluator($target) : null);
        if ($evaluator === null) {
            return null;
        }
        $descriptor = $evaluator->descriptor();
        $description = new CallDescription($target, $descriptor->signature, $context->configuration->target, source: $call->source, declarations: new Declarations($context->index->program));
        if ($evaluator instanceof ArgumentDemand) {
            $description = $this->demand($evaluator, $descriptor, $description, $caller, $call, $depth);
        }

        try {
            $decision = $evaluator->evaluate($description);
        } catch (Throwable $error) {
            throw new \Deriver\Exception\EvaluationContractException($descriptor->id . ': ' . $error->getMessage(), previous: $error);
        }
        if ($decision->kind === Call\DecisionKind::Declined) {
            return null;
        }
        $context->evaluatorApplications++;
        if ($decision->kind !== Call\DecisionKind::Handled || $decision->plan === null) {
            return $context->reference($caller, 'call:' . $target, $call->source, reason: 'UNSUPPORTED_EVALUATOR_CASE');
        }
        $violations = (new PlanValidation())->validate($decision->plan, $descriptor->signature, $context->evaluators->state->slots);
        if ($violations !== []) {
            throw new \Deriver\Exception\EvaluationContractException($descriptor->id . ': ' . implode(', ', $violations));
        }
        $source = new SourceRef($call->source->snapshotId, 'evaluator:' . $descriptor->id . '@' . $descriptor->version, 0, 0);
        $declaration = $descriptor->useSourceSignature ? $context->index->program->callable($target) : null;
        $body = (new PlanCompiler($source))->compile($descriptor, $decision->plan, $declaration);
        $context->evaluationEvidence->offsetSet($body, new Evidence\Node(Evidence\EvidenceKind::EvaluatorApplication, $this->engine->context->provenance->inputs(array_map(static fn (BoundArgument $argument): Term => $argument->value, $description->arguments->arguments)), $call->source, ['id' => $descriptor->id, 'version' => $descriptor->version, 'operation' => $call->operation, 'name' => $target]));
        return new BodyIndex($body);
    }

    /**
     * Requests only parameters used to select a trusted evaluator plan.
     * @throws \Deriver\Exception\EvaluationContractException If the evaluator violates its demand contract
     */
    public function demand(ArgumentDemand $evaluator, Call\CallDescriptor $descriptor, CallDescription $description, Scope\Frame $caller, Instruction $call, int $depth): CallDescription
    {
        $context = $this->engine->context;
        $arguments = $description->arguments->arguments;
        try {
            $demanded = $evaluator->demand($description);
        } catch (Throwable $error) {
            throw new \Deriver\Exception\EvaluationContractException($descriptor->id . ': ' . $error->getMessage(), previous: $error);
        }
        $actuals = $demanded === [] ? [] : (new Invocation\Arguments($this->engine))->actuals($caller, $call, $depth);
        foreach ($demanded as $name) {
            $position = array_search($name, array_column($descriptor->signature->parameters, 'name'), true);
            if ($position === false) {
                throw new \Deriver\Exception\EvaluationContractException($descriptor->id . ': demand names an absent parameter ' . $name);
            }
            $actual = $actuals[$name] ?? $actuals[$position] ?? $actuals['*'] ?? null;
            $value = $actual instanceof Scope\Binding ? $actual->value($this->engine, 'mixed', $depth) : ($actual ?? $descriptor->signature->parameters[$position]->default ?? Term::parameter($name));
            $arguments[$name] = new BoundArgument($name, $value, supplied: $actual !== null);
        }
        return new CallDescription($description->symbol, $descriptor->signature, $context->configuration->target, arguments: new ArgumentBindings($arguments, true), source: $call->source, declarations: $description->declarations);
    }

    /**
     * Selects an actual argument by its formal name or position.
     */
    public function actual(Instruction $call, string $name, int $position): ?string
    {
        foreach ($call->arguments as $index => $argument) {
            if ($argument->name === $name || $argument->name === null && $index === $position) {
                return $argument->register;
            }
        }
        return null;
    }
}
