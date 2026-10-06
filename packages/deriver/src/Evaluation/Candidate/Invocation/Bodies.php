<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Invocation;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Models;

/**
 * The only candidate entry to source or model implementations.
 * @visibility root
 */
final class Bodies
{
    /**
     * Uses the current dependency context.
     */
    public function __construct(private readonly Derivation $engine)
    {
    }

    /**
     * Selects once per call, bindings and remaining depth, before inspecting effects.
     */
    public function select(Frame $caller, Instruction $call, string $target, int $depth): Body
    {
        $context = $this->engine->context;
        $key = $caller->identity . ':' . $call->id . ':' . $target . ':' . $depth;
        if (isset($context->implementations[$key])) {
            return $context->implementations[$key];
        }
        $model = $call->operation === 'invoke' ? (new Rules($this->engine))->apply($caller, $call, $depth, $target) : null;
        $model ??= (new Models($this->engine))->graph($caller, $call, $target, $depth);
        return $context->implementations[$key] = new Body($model ?? $context->index->graph($target), $model !== null);
    }
}
