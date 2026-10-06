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
            $arguments = $description->arguments->arguments;
            foreach ($model->demand($description) as $name) {
                $position = array_search($name, array_column($descriptor->signature->parameters, 'name'), true);
                $actual = $this->actual($call, $name, $position === false ? -1 : $position);
                $value = $actual === null ? ($arguments[$name]->value ?? Term::parameter($name)) : $this->engine->value($caller, $actual, $depth);
                $arguments[$name] = new BoundArgument($name, $value, supplied: $actual !== null);
            }
            $description = new CallDescription($target, $descriptor->signature, $context->configuration->target, arguments: new ArgumentBindings($arguments, true), dependencyVersions: $context->configuration->dependencyVersions, source: $call->source, declarations: $description->declarations);
        }
        $decision = $model->describe($description);
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
        return new Graph((new PlanCompiler($source))->compile($descriptor, $decision->plan, $declaration));
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
