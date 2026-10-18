<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation;

use Deriver\Deriver\Evaluator\CallEvaluation;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * The only candidate entry to source or evaluator implementations.
 * @visibility parent
 */
final class Bodies
{
    /**
     * Uses the current dependency context.
     */
    public function __construct(private readonly Evaluator $engine)
    {
    }

    /**
     * Selects once per call and bindings, before inspecting effects.
     */
    public function select(Frame $caller, Instruction $call, string $target, int $depth): Body
    {
        $context = $this->engine->context;
        $key = $caller->identity . ':' . $call->id . ':' . $target;
        if (isset($context->implementations[$key])) {
            return $context->implementations[$key];
        }
        $request = new \Deriver\Deriver\Evaluator\Micro\Request($this->engine, $caller, $call, $depth, $target);
        $builtin = new CallEvaluation($this->engine);
        $evaluator = (new \Deriver\Deriver\Evaluator\Micro\Dispatch())->evaluate($request, $builtin);
        return $context->implementations[$key] = new Body($evaluator ?? $context->index->graph($target), $evaluator !== null);
    }
}
