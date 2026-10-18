<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Checks callable identities against captured declarations without host reflection.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class CallableCheck
{
    /**
     * @param Context $context Captured declaration world
     */
    public function __construct(public readonly Context $context)
    {
    }
    /**
     * Recognizes proven callables while retaining unresolved external declarations.
     * @param Term $value Evaluated callback candidate
     * @return Term Boolean or symbolic callable predicate
     */
    public function evaluate(Term $value): Term
    {
        if (in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Callable, \Deriver\Deriver\Evaluator\TermKind::CallableMethod], true)) {
            return Term::constant(true);
        }
        $method = $this->method($value);
        if ($method !== null) {
            return $method;
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant) {
            if (!is_string($value->literal)) {
                return Term::constant(false);
            }
            if ($this->context->program->callable($value->literal) !== null || (new Library())->evaluator($value->literal) !== null || isset($this->context->evaluators->evaluators[strtolower($value->literal)])) {
                return Term::constant(true);
            }
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Object && is_string($value->attributes['class'] ?? null)) {
            $class = $value->attributes['class'];
            if (isset($this->context->program->classes()[strtolower($class)])) {
                return Term::constant((new Dispatch($this->context->program))->method($class, '__invoke') !== null);
            }
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Array && !$this->pair($value) && ($value->attributes['open'] ?? false) === false) {
            return Term::constant(false);
        }
        return new Term(\Deriver\Deriver\Evaluator\TermKind::Intrinsic, 'is_callable', [$value], ['type' => 'bool']);
    }

    /**
     * Checks the exact two positional entries required by an array callable.
     * @param Term $value Candidate callback array
     * @return bool Whether the complete shape has exactly keys zero and one
     */
    public function pair(Term $value): bool
    {
        return in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::CallableMethod], true) && ($value->attributes['open'] ?? false) !== true && count($value->operands) === 2 && isset($value->operands[0], $value->operands[1]);
    }

    /**
     * Checks known public method pairs without assuming access to a private lexical scope.
     * @param Term $value Method string or callable pair
     * @return Term|null Proven predicate, symbolic access test, or no method form
     */
    public function method(Term $value): ?Term
    {
        $receiver = null;
        $method = null;
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($value->literal) && str_contains($value->literal, '::')) {
            [$class, $method] = explode('::', $value->literal, 2);
            $receiver = Term::constant($class);
        } elseif ($this->pair($value)) {
            $receiver = $value->operands[0];
            $name = $value->operands[1];
            $method = $name->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($name->literal) ? $name->literal : null;
        } else {
            return null;
        }
        $class = $receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Constant ? $receiver->literal : ($receiver->attributes['class'] ?? null);
        $symbol = is_string($class) && $method !== null ? (new Dispatch($this->context->program))->method($class, $method) : null;
        $body = $symbol === null ? null : $this->context->program->callable($symbol);
        if ($body !== null && $body->visibility === \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Public) {
            return Term::constant($receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Object || $body->static);
        }
        return new Term(\Deriver\Deriver\Evaluator\TermKind::Intrinsic, 'is_callable', [$value], ['type' => 'bool']);
    }
}
