<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Constant;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\Solver\Call\Creation\Builtins;
use Deriver\Internal\Solver\Call\Dispatch;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;

/**
 * Separates lexical class references from runtime class strings and object names.
 * @visibility root
 */
final class ClassNames
{
    /**
     * @param Context $context Captured declaration world
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Resolves the class operand of an ordinary constant fetch.
     * @param Term $value Evaluated operand
     * @param bool $literal Whether the source operand is a literal class name
     * @param CallableIR $caller Lexical declaration
     * @param State $state Runtime called class
     * @return Term Class string, definite Error, or unresolved class value
     */
    public function resolve(Term $value, bool $literal, CallableIR $caller, State $state): Term
    {
        $object = $this->object($value);
        if (!$literal && $object !== null) {
            return Term::constant($object, $value->isSecret());
        }
        if ($value->kind === 'constant' && is_string($value->literal)) {
            $name = $literal ? (new Dispatch($this->context->program))->className($value->literal, $caller->className, $state->lateStaticClass) : ltrim($value->literal, '\\');
            if ($name === '' || !$literal && in_array(strtolower($name), ['self','parent','static'], true)) {
                return new Term('throwable', 'Error');
            }
            return Term::constant($name, $value->isSecret());
        }
        return in_array($value->kind, ['constant','array','uninitialized'], true) ? new Term('throwable', 'Error') : Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', dependencies:[$value]);
    }

    /**
     * Applies the object-only runtime form of the syntactic ::class operator.
     * @param Term $value Evaluated receiver
     * @return Term Exact runtime class, TypeError, or an explicit unresolved value
     */
    public function runtime(Term $value): Term
    {
        $class = $this->object($value);
        if ($class !== null) {
            return Term::constant($class, $value->isSecret());
        }
        return in_array($value->kind, ['constant','array','uninitialized'], true) ? new Term('throwable', 'TypeError') : Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', dependencies:[$value]);
    }

    /**
     * Finds the declaration spelling used when the constant name is evaluated at runtime.
     * @param string $name Requested class spelling
     * @return string|null Captured or supported built-in class name
     */
    public function canonical(string $name): ?string
    {
        return $this->context->program->classes()[strtolower($name)]->name ?? (strcasecmp($name, 'Closure') === 0 ? 'Closure' : (new Builtins())->name($name));
    }

    /**
     * Reads an established runtime object class without interpreting a declared subtype bound.
     * @param Term $value Frozen operand
     * @return string|null Exact runtime class, when established
     */
    public function object(Term $value): ?string
    {
        if ($value->kind === 'closure') {
            return 'Closure';
        }
        $class = $value->attributes['class'] ?? null;
        return in_array($value->kind, ['object','enum'], true) && is_string($class) ? $class : null;
    }
}
