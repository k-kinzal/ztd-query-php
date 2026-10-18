<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Micro;

use Deriver\Deriver\Evaluator\Call\BoundInstruction;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Origins;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Exception\EvaluationContractException;
use Override;

/**
 * Offers direct return observations to the same function evaluator registry as calls.
 * @implements MicroEvaluator<Term>
 * @visibility parent
 */
final class ReturnEvaluator implements MicroEvaluator
{
    /**
     * Selects a replacement before opening any return or parameter dependency.
     */
    public function select(Evaluator $engine, Frame $frame, int $depth): Term
    {
        $site = new Site(
            'function',
            strtolower(ltrim($frame->graph->body->symbol, '\\')),
            $frame->graph->body->source,
            fn (int|string $key): Term => $this->input($engine, $frame, $key, $depth),
            fn (int|string $key): Term => (new \Deriver\Deriver\Evaluator\Predicate\Projection($engine->context->provenance))->value($this->input($engine, $frame, $key, $depth, true))
        );
        return (new Dispatch())->evaluate(new Request($engine, $frame, null, $depth, site: $site), $this);
    }

    /**
     * Falls back to the captured return dependencies.
     */
    #[Override]
    public function evaluate(Request $request): Term
    {
        $engine = $request->engine;
        $frame = $request->frame;
        $depth = $request->depth;
        $branches = [fn (): Term => $engine->returns($frame, $depth)];
        foreach ($engine->context->index->program->variants($frame->graph->body->symbol) as $variant) {
            $graph = $engine->context->index->graph($variant);
            if ($graph !== null) {
                $branches[] = fn (): Term => $engine->returns(new Frame($graph, 'source:' . $variant, $frame->bindings, $frame->properties, $frame->calls, $frame->invocation, calledClass: $frame->calledClass), $depth);
            }
        }
        return (new \Deriver\Deriver\Evaluator\Enumeration\Choices($engine->context->provenance))->make(array_map(static fn (Term $value): array => [$value, []], $engine->context->search->all($branches)));
    }

    /**
     * Resolves a formal parameter only when a replacement requests it.
     * @throws EvaluationContractException If the parameter is absent
     */
    public function input(Evaluator $engine, Frame $frame, int|string $key, int $depth, bool $truth = false): Term
    {
        $parameters = $frame->graph->body->parameters;
        $parameter = is_int($key) ? ($parameters[$key] ?? null) : (array_column($parameters, null, 'name')[$key] ?? null);
        if ($parameter === null) {
            throw new EvaluationContractException('The micro evaluator requested an absent parameter: ' . $key);
        }
        return (new Origins($engine, $truth))->parameter($frame, new BoundInstruction('parameter:' . $parameter->name, 'local', $parameter->source ?? $frame->graph->body->source, name: $parameter->name), $depth);
    }
}
