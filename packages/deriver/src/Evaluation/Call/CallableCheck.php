<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\Evaluation\Context;
use Deriver\Model\Builtin\Library;
use Deriver\Value\Term;

/**
 * Checks callable identities against captured declarations without host reflection.
 * @visibility root
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
        if (in_array($value->kind, ['closure', 'callable', 'callable-method'], true)) {
            return Term::constant(true, $value->isSecret());
        }
        $method = $this->method($value);
        if ($method !== null) {
            return $method;
        }
        if ($value->kind === 'constant') {
            if (!is_string($value->literal)) {
                return Term::constant(false, $value->isSecret());
            }
            if ($this->context->program->callable($value->literal) !== null || (new Library())->model($value->literal) !== null || isset($this->context->models->models[strtolower($value->literal)])) {
                return Term::constant(true, $value->isSecret());
            }
        }
        if ($value->kind === 'object' && is_string($value->attributes['class'] ?? null)) {
            $class = $value->attributes['class'];
            if (isset($this->context->program->classes()[strtolower($class)])) {
                return Term::constant((new Dispatch($this->context->program))->method($class, '__invoke') !== null, $value->isSecret());
            }
        }
        if ($value->kind === 'array' && !$this->pair($value) && ($value->attributes['open'] ?? false) === false) {
            return Term::constant(false, $value->isSecret());
        }
        return new Term('intrinsic', 'is_callable', [$value], ['type' => 'bool']);
    }

    /**
     * Checks the exact two positional entries required by an array callable.
     * @param Term $value Candidate callback array
     * @return bool Whether the complete shape has exactly keys zero and one
     */
    public function pair(Term $value): bool
    {
        return in_array($value->kind, ['array', 'callable-method'], true) && ($value->attributes['open'] ?? false) !== true && count($value->operands) === 2 && isset($value->operands[0], $value->operands[1]);
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
        if ($value->kind === 'constant' && is_string($value->literal) && str_contains($value->literal, '::')) {
            [$class, $method] = explode('::', $value->literal, 2);
            $receiver = Term::constant($class);
        } elseif ($this->pair($value)) {
            $receiver = $value->operands[0];
            $name = $value->operands[1];
            $method = $name->kind === 'constant' && is_string($name->literal) ? $name->literal : null;
        } else {
            return null;
        }
        $class = $receiver->kind === 'constant' ? $receiver->literal : ($receiver->attributes['class'] ?? null);
        $symbol = is_string($class) && $method !== null ? (new Dispatch($this->context->program))->method($class, $method) : null;
        $body = $symbol === null ? null : $this->context->program->callable($symbol);
        if ($body !== null && $body->visibility === 'public') {
            return Term::constant($receiver->kind === 'object' || $body->static, $value->isSecret());
        }
        return new Term('intrinsic', 'is_callable', [$value], ['type' => 'bool']);
    }
}
