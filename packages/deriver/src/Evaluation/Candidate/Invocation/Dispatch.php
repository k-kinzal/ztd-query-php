<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Invocation;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Value\Term;

/**
 * Separates declaration discovery from dispatch on correlated receiver candidates.
 * @visibility root
 */
final class Dispatch
{
    /**
     * Uses the current dependency context.
     */
    public function __construct(private readonly Derivation $engine)
    {
    }

    /**
     * Dispatches the requested dependency with the selected receiver still bound.
     * @param callable(string, ?Term, ?Term): Term $dependency Target, receiver and closure
     */
    public function apply(Frame $frame, Instruction $call, int $depth, callable $dependency): Term
    {
        $index = $this->engine->context->index;
        $target = $index->target($frame->graph, $call);
        if ($call->operation === 'invoke-method') {
            $receiver = $this->engine->value($frame, $call->operands[0], $depth);
            $name = $this->engine->value($frame, $call->operands[1], $depth);
            return (new Choices())->apply('method-target', [$receiver, $name], function (array $values) use ($frame, $target, $dependency): Term {
                [$object, $method] = $values;
                if ($object->kind === 'throwable') {
                    return $object;
                }
                if (in_array($object->kind, ['constant', 'array'], true)) {
                    return new Term('throwable', 'Error', [$object]);
                }
                $class = (string) ($object->attributes['type'] ?? '');
                $selected = $class !== '' && $method->kind === 'constant' && is_string($method->literal) ? $this->target($frame, $class, $method->literal) : $target;
                return $dependency($selected, $object, null);
            }, $this->engine->context->budget->partitions);
        }
        if ($call->operation === 'invoke' && $target === '') {
            $callable = $this->engine->value($frame, $call->operands[0], $depth);
            return (new Choices())->apply('function-target', [$callable], static function (array $values) use ($dependency): Term {
                $value = $values[0];
                return $dependency(in_array($value->kind, ['constant', 'closure'], true) && is_string($value->literal) ? $value->literal : '', null, $value);
            }, $this->engine->context->budget->partitions);
        }
        return $dependency($target, null, null);
    }
    /**
     * Preserves lexical private methods while dispatching other methods on the receiver.
     */
    public function target(Frame $frame, string $class, string $method): string
    {
        $index = $this->engine->context->index;
        return (new \Deriver\Evaluation\Call\Member\Access($index->program))->target($class, $frame->graph->body->className, $method) ?? $index->method($class, $method);
    }

}
