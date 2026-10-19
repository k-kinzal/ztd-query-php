<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Separates declaration discovery from dispatch on correlated receiver candidates.
 * @visibility parent
 */
final class Dispatch
{
    /**
     * Uses the current dependency context.
     */
    public function __construct(private readonly Evaluator $engine)
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
        if (in_array($call->operation, ['invoke-static', 'new'], true)) {
            $raw = $index->literal($frame->graph, $call->operands[0]);
            $class = $index->className($raw, $frame->graph->body->className, $frame->calledClass);
            $name = $call->operation === 'new' ? '__construct' : $index->literal($frame->graph, $call->operands[1]);
            $target = $index->method($class, $name);
            $receiver = $frame->bindings['this'] ?? null;
            if ($receiver instanceof \Deriver\Deriver\Evaluator\Scope\Binding) {
                $receiver = $receiver->value($this->engine, 'mixed', $depth);
            }
            return $dependency($target, $call->operation === 'invoke-static' && in_array(strtolower($raw), ['parent', 'self', 'static'], true) ? $receiver : null, null);
        }
        if ($call->operation === 'invoke-method') {
            $receiver = $this->engine->value($frame, $call->operands[0], $depth);
            $name = $this->engine->value($frame, $call->operands[1], $depth);
            return (new Choices($this->engine->context->provenance, $this->engine->context->search))->apply('method-target', [$receiver, $name], function (array $values) use ($frame, $target, $dependency): Term {
                [$object, $method] = $values;
                if ($object->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
                    return $object;
                }
                if (in_array($object->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Array], true)) {
                    return new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error', [$object]);
                }
                $class = $this->engine->context->index->receiverClass((string) ($object->attributes['class'] ?? $object->attributes['type'] ?? ''));
                $selected = $class !== '' && $method->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($method->literal) ? $this->target($frame, $class, $method->literal) : $target;
                return $dependency($selected, $object, null);
            }, $this->engine->context->budget->partitions);
        }
        if ($call->operation === 'invoke' && ($target === '' || str_contains($target, '::'))) {
            $callable = $this->engine->value($frame, $call->operands[0], $depth);
            return (new Choices($this->engine->context->provenance, $this->engine->context->search))->apply('function-target', [$callable], function (array $values) use ($frame, $dependency): Term {
                $value = $values[0]->kind === \Deriver\Deriver\Evaluator\TermKind::Callable ? $values[0]->operands[0] : $values[0];
                if (in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::CallableMethod], true) && count($value->operands) === 2) {
                    [$receiver, $method] = array_values($value->operands);
                    if ($method->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($method->literal)) {
                        $class = $receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($receiver->literal) ? $receiver->literal : $this->engine->context->index->receiverClass((string) ($receiver->attributes['type'] ?? ''));
                        return $dependency($class === '' ? '' : $this->target($frame, $class, $method->literal), $receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Object ? $receiver : null, $value);
                    }
                }
                if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Object) {
                    return $dependency($this->target($frame, (string) ($value->attributes['type'] ?? ''), '__invoke'), $value, $value);
                }
                return $dependency(in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Closure], true) && is_string($value->literal) ? $value->literal : '', null, $value);
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
        return (new \Deriver\Deriver\Lowerer\Hierarchy\Access($index->program))->target($class, $frame->graph->body->className, $method) ?? $index->method($class, $method);
    }

}
