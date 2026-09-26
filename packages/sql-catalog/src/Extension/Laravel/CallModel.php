<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use PhpParser\Node\Expr;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Extension\Model\CallContext;
use SqlCatalog\Core\Extension\Model\ModelContext;

/**
 * Registers Laravel call semantics through the framework-neutral model API.
 *
 * @visibility root
 */
final class CallModel
{
    private BuilderCalls $builders;

    /**
     * Creates fresh allocation state for this analysis and uses its shared callback runner.
     */
    public function __construct(private readonly ModelContext $context, private readonly \SqlCatalog\Core\Sql\Dialects $dialects = new \SqlCatalog\Core\Sql\Dialects())
    {
        $this->builders = new BuilderCalls($context->index, $context->dialect, new CallbackModel($context->index, $context->callbacks, $this->dialects), $this->dialects);
    }

    /**
     * A Laravel factory, mutation or execution result; null leaves other APIs to the core.
     */
    public function evaluate(CallContext $call): ?Domain
    {
        if ($call->name === null) {
            return null;
        }
        if ($call->sink?->model === 'laravel.builder') {
            $receiver = $call->receiver ?? Domain::of(new ObjectTerm($call->className ?? ModelMetadata::MODEL, state: (new QueryState(['model' => Domain::literal($call->className)]))->array()));

            return $this->builders->execution($receiver, strtolower($call->name), $call->arguments, $call->environment);
        }
        if ($call->sink !== null) {
            return null;
        }
        $value = null;
        if ($call->receiver !== null) {
            $value = $this->builders->methodCall($call->receiver, $call->name, $call->arguments, $call->environment, $call->node, $call->scope, $call->expressions);
        } elseif ($call->node instanceof Expr\StaticCall && $call->className !== null) {
            $value = $this->builders->staticCall($call->className, $call->name, $call->arguments, $call->environment, $call->node, $call->scope, $call->expressions);
        }

        return $value !== null && !$this->builders->positional(array_values($call->node->getArgs()))
            ? $this->builders->unsupported($value, $call->environment, 'Named or unpacked Laravel arguments are not modelled')
            : $value;
    }

    /**
     * Framework inheritance known without scanning Illuminate's source tree.
     */
    public function matchesClass(string $class, string $expected): bool
    {
        return ($expected === BuilderCalls::CONNECTION && $this->builders->isConnection($class))
            || ($expected === ModelMetadata::MODEL && (new ModelMetadata($this->context->index))->recognizes($class));
    }
}
