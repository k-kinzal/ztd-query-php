<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation;

use Deriver\Deriver\Evaluator\Calls;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Terminator;

/**
 * Adapts evaluator-requested callbacks to the ordinary call dispatcher and binder.
 * @visibility parent
 */
final class Callback
{
    /**
     * Retains the call context used by a evaluator's callback demands.
     */
    public function __construct(private readonly Evaluator $engine, private readonly Frame $owner, private readonly Instruction $at, private readonly int $depth)
    {
    }

    /**
     * Expands a captured callable without invoking application PHP.
     * @param array<int|string, Term> $arguments Demanded callback arguments
     */
    public function value(Term $callback, array $arguments): Term
    {
        $engine = $this->engine;
        $owner = $this->owner;
        $at = $this->at;
        $depth = $this->depth;
        $values = [$callback, ...array_values($arguments)];
        $identity = $owner->identity . ':callback:' . $at->id . ':' . (new Identity())->key(Term::array($values));
        $definitions = [];
        foreach ($values as $index => $value) {
            $definitions[] = new \Deriver\Deriver\Evaluator\Call\BoundInstruction('callback:' . $index, 'constant', $at->source, 'callback:' . $index, constant: $value);
        }
        $actuals = [];
        foreach (array_keys($arguments) as $index => $key) {
            $actuals[] = new Argument('callback:' . ($index + 1), is_string($key) ? $key : null);
        }
        $call = new \Deriver\Deriver\Evaluator\Call\BoundInstruction('callback-call', 'invoke', $at->source, 'callback-result', ['callback:0'], arguments: $actuals);
        $definitions[] = $call;
        $graph = new CallableGraph($owner->graph->body->symbol, [], [new BasicBlock(0, $definitions, new Terminator(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::Return, $call->result))], $at->source, className: $owner->graph->body->className);
        $frame = new Frame(new BodyIndex($graph), $identity, calls: $owner->calls, origin: $owner->origin, calledClass: $owner->calledClass);
        $engine->context->frames[$identity] = $frame;
        return (new Calls($engine))->value($frame, $call, $depth);
    }
}
