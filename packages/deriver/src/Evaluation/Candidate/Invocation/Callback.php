<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Invocation;

use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Candidate\Calls;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Graph;
use Deriver\Value\Identity;
use Deriver\Value\Term;

/**
 * Adapts model-requested callbacks to the ordinary call dispatcher and binder.
 * @visibility root
 */
final class Callback
{
    /**
     * Retains the call context used by a model's callback demands.
     */
    public function __construct(private readonly Derivation $engine, private readonly Frame $owner, private readonly Instruction $at, private readonly int $depth)
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
            $definitions[] = new Instruction('callback:' . $index, 'constant', $at->source, 'callback:' . $index, constant: $value);
        }
        $actuals = [];
        foreach (array_keys($arguments) as $index => $key) {
            $actuals[] = new Argument('callback:' . ($index + 1), is_string($key) ? $key : null);
        }
        $call = new Instruction('callback-call', 'invoke', $at->source, 'callback-result', ['callback:0'], arguments: $actuals);
        $definitions[] = $call;
        $graph = new CallableGraph($owner->graph->body->symbol, [], [new BasicBlock(0, $definitions, new Terminator('return', $call->result))], $at->source, className: $owner->graph->body->className);
        $frame = new Frame(new Graph($graph), $identity, calls: $owner->calls, origin: $owner->origin, calledClass: $owner->calledClass);
        $engine->context->frames[$identity] = $frame;
        return (new Calls($engine))->value($frame, $call, $depth);
    }
}
