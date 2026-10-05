<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Binding\BoundArgument;
use Deriver\Model\Builtin\Library;
use Deriver\Model\CallDescription;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Compilation\PlanValidation;
use Deriver\Model\DemandModel;
use Deriver\Model\Registration\Declarations;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use Throwable;

/**
 * Selects a replacement before demanding any source body or irrelevant input.
 * @visibility root
 */
final class Models
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Derivation $engine)
    {
    }

    /**
     * Retrieves indexed semantics without evaluating source instructions.
     * @throws \Deriver\Exception\ModelContractException If a model violates its contract
     */
    public function graph(Frame $caller, Instruction $call, string $target, int $depth): Graph|Term|null
    {
        $context = $this->engine->context;
        $key = (new CallableIdentity())->key($target);
        $model = $context->models->models[$key] ?? ($context->configuration->standardModels ? (new Library())->model($target) : null);
        if ($model === null) {
            return null;
        }
        $descriptor = $model->descriptor();
        $description = new CallDescription($target, $descriptor->signature, $context->configuration->target, dependencyVersions: $context->configuration->dependencyVersions, source: $call->source, declarations: new Declarations($context->index->program));
        if ($model instanceof DemandModel) {
            $description = $this->demand($model, $descriptor, $description, $caller, $call, $depth);
        }

        try {
            $decision = $model->describe($description);
        } catch (Throwable $error) {
            throw new \Deriver\Exception\ModelContractException($descriptor->id . ': ' . $error->getMessage(), previous: $error);
        }
        if ($decision->kind === 'declined') {
            return null;
        }
        $context->modelApplications++;
        if ($decision->kind !== 'handled' || $decision->plan === null) {
            return $context->reference($caller, 'call:' . $target, $call->source, reason: 'UNSUPPORTED_MODEL_CASE');
        }
        $violations = (new PlanValidation())->validate($decision->plan, $descriptor->signature, $context->models->state->slots);
        if ($violations !== []) {
            throw new \Deriver\Exception\ModelContractException($descriptor->id . ': ' . implode(', ', $violations));
        }
        $source = new SourceRef($call->source->snapshotId, 'model:' . $descriptor->id . '@' . $descriptor->version, 0, 0);
        $declaration = $descriptor->useSourceSignature ? $context->index->program->callable($target) : null;
        return new Graph((new PlanCompiler($source))->compile($descriptor, $decision->plan, $declaration), new \Deriver\Result\Evidence\Node('model-application', Evidence\Provenance::inputs(array_map(static fn (BoundArgument $argument): Term => $argument->value, $description->arguments->arguments)), $call->source, ['id' => $descriptor->id, 'version' => $descriptor->version, 'operation' => $call->operation, 'name' => $target]));
    }

    /**
     * Requests only parameters used to select a trusted model plan.
     * @throws \Deriver\Exception\ModelContractException If the model violates its demand contract
     */
    public function demand(DemandModel $model, \Deriver\Model\ModelDescriptor $descriptor, CallDescription $description, Frame $caller, Instruction $call, int $depth): CallDescription
    {
        $context = $this->engine->context;
        $arguments = $description->arguments->arguments;
        try {
            $demanded = $model->demand($description);
        } catch (Throwable $error) {
            throw new \Deriver\Exception\ModelContractException($descriptor->id . ': ' . $error->getMessage(), previous: $error);
        }
        $actuals = $demanded === [] ? [] : (new Invocation\Arguments($this->engine))->actuals($caller, $call, $depth);
        foreach ($demanded as $name) {
            $position = array_search($name, array_column($descriptor->signature->parameters, 'name'), true);
            if ($position === false) {
                throw new \Deriver\Exception\ModelContractException($descriptor->id . ': demand names an absent parameter ' . $name);
            }
            $actual = $actuals[$name] ?? $actuals[$position] ?? $actuals['*'] ?? null;
            $value = $actual instanceof Binding ? $actual->value($this->engine, 'mixed', $depth) : ($actual ?? $descriptor->signature->parameters[$position]->default ?? Term::parameter($name));
            $arguments[$name] = new BoundArgument($name, $value, supplied: $actual !== null);
        }
        return new CallDescription($description->symbol, $descriptor->signature, $context->configuration->target, arguments: new ArgumentBindings($arguments, true), dependencyVersions: $context->configuration->dependencyVersions, source: $call->source, declarations: $description->declarations);
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
