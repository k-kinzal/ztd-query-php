<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\State;
use Deriver\Reference\SourceRef;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Checks runtime declaration types separately from symbolic value knowledge.
 * @visibility root
 */
final class TypeBinding
{
    /**
     * @param Context $context Declaration hierarchy
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Validates or coerces an argument under the calling file's strict_types mode.
     * @param Term $value Actual argument
     * @param string $type Declared PHP type
     * @param bool $strict Calling-file scalar coercion mode
     * @return TypeCheck Valid value and exceptional alternatives
     */
    public function check(Term $value, string $type, bool $strict): TypeCheck
    {
        if ($type === 'mixed' || $type === '' || $type === 'void') {
            return new TypeCheck($value);
        }
        $types = explode('|', $type);
        $accepted = $this->matching($value, $types);
        if ($accepted !== null) {
            return $accepted;
        }
        if (!$strict && in_array('string', $types, true) && $value->kind === 'object' && (new Dispatch($this->context->program))->method((string) ($value->attributes['class'] ?? ''), '__toString') !== null) {
            return new TypeCheck(Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', 'string', [$value]), true, coercion: $value);
        }
        if (!$strict && $value->kind === 'constant' && $value->literal !== null) {
            foreach ($this->preference($value, $types) as $scalar) {
                if (in_array($scalar, $types, true) && $this->coercible($value, $scalar)) {
                    return $this->coerce($value, $scalar);
                }
            }
        }
        if (in_array('callable', $types, true) && (new CallableCheck($this->context))->evaluate($value)->kind !== 'constant') {
            return new TypeCheck($value, true, coercion: $value, operation: 'callable-validation');
        }
        if ($value->kind === 'constant' || in_array($value->kind, ['array', 'object', 'closure', 'enum'], true)) {
            return new TypeCheck($value, true, true);
        }
        return new TypeCheck(new Term('type-refinement', $type, [$value], ['type' => $type]), true);
    }

    /**
     * Recognizes values whose runtime type already satisfies one declaration type.
     * @param Term $value Value
     * @param string $type One union arm
     * @return bool Whether acceptance is established
     */
    public function accepts(Term $value, string $type): bool
    {
        if ($type === 'callable' && (new CallableCheck($this->context))->evaluate($value)->literal === true) {
            return true;
        }
        if ($type === 'object' && in_array($value->kind, ['object', 'closure', 'enum'], true)) {
            return true;
        }
        if (str_contains($type, '&')) {
            foreach (explode('&', $type) as $part) {
                if (!$this->accepts($value, trim($part, '()'))) {
                    return false;
                }
            }
            return true;
        }
        if (($value->attributes['type'] ?? '') === $type) {
            return true;
        }
        if ($value->kind === 'constant') {
            return $this->scalar($value, $type);
        }
        if ($value->kind === 'array') {
            return $type === 'array' || $type === 'iterable';
        }
        if ($value->kind === 'closure') {
            return in_array($type, ['Closure', 'object', 'callable'], true);
        }
        $class = $value->attributes['class'] ?? null;
        return in_array($value->kind, ['object', 'enum'], true) && is_string($class) && (new Dispatch($this->context->program))->subtype($class, $type);
    }

    /**
     * Checks the subset of weak scalar conversions with verified target behavior.
     * @param Term $value Concrete scalar
     * @param string $type Required scalar type
     * @return bool Whether coercion is legal
     */
    public function coercible(Term $value, string $type): bool
    {
        if ($type === 'string' || $type === 'bool') {
            return $value->literal !== null;
        }
        if (is_string($value->literal)) {
            $number = (new NumericString())->parse($value->literal);
            if ($number === null || is_int($number)) {
                return $number !== null;
            }
        }
        return $type !== 'int' || (is_finite((float) $value->literal) && (float) $value->literal >= -9223372036854775808.0 && (float) $value->literal < 9223372036854775808.0);
    }
    /**
     * Resolves declaration-relative class types in the bound call context.
     * @param string $type Declaration spelling
     * @param CallableGraph $callable Lexical declaration
     * @param State $state Bound receiver context
     * @return string Resolved union or intersection
     */
    public function declared(string $type, CallableGraph $callable, State $state): string
    {
        return $this->scope($type, $callable->className, $state->lateStaticClass);
    }

    /**
     * Resolves relative class names in a union or intersection using the declaration owner.
     * @param string $type Declared type expression
     * @param string $class Lexical declaration class
     * @param string $lateStaticClass Runtime binding for static return types
     * @return string Normalized resolved type
     */
    public function scope(string $type, string $class, string $lateStaticClass): string
    {
        $arms = [];
        foreach (explode('|', $type) as $arm) {
            $parts = [];
            foreach (explode('&', $arm) as $part) {
                $parts[] = (new Dispatch($this->context->program))->className(trim($part, '()'), $class, $lateStaticClass);
            }
            $arms[] = implode('&', $parts);
        }
        return implode('|', $arms);
    }
    /**
     * Checks one concrete scalar against a declaration arm.
     * @param Term $value Concrete scalar
     * @param string $type Declaration arm
     * @return bool Whether the scalar already satisfies the type
     */
    public function scalar(Term $value, string $type): bool
    {
        return match ($type) {
            'int' => is_int($value->literal),
            'float' => is_float($value->literal) || is_int($value->literal),
            'string' => is_string($value->literal),
            'bool' => is_bool($value->literal),
            'true' => $value->literal === true,
            'false' => $value->literal === false,
            'null' => $value->literal === null,
            default => false,
        };
    }

    /**
     * Selects numeric-string union coercion without discarding its float representation.
     * @param Term $value Concrete scalar
     * @param list<string> $types Declaration union
     * @return list<string> Target scalar preference
     */
    public function preference(Term $value, array $types): array
    {
        $floating = is_string($value->literal) && is_float((new NumericString())->parse($value->literal));
        return $floating && in_array('int', $types, true) && in_array('float', $types, true) ? ['float', 'int', 'string', 'bool'] : ['int', 'float', 'string', 'bool'];
    }

    /**
     * Retains precision-loss diagnostics from weak integer conversion.
     * @param Term $value Accepted concrete scalar
     * @param string $type Selected scalar type
     * @return TypeCheck Coerced value and target diagnostic
     */
    public function coerce(Term $value, string $type): TypeCheck
    {
        $result = (new Operations())->cast($type, $value);
        $warning = $type === 'int' && is_int($result->literal) && (float) $value->literal !== (float) $result->literal;
        return new TypeCheck($result, diagnostic: $warning);
    }

    /**
     * Records a checked coercion's target diagnostic at the operation that requested it.
     * @param TypeCheck $check Applied type conversion
     * @param SourceRef $source Argument, return, or property origin
     * @param State|null $state State affected by an implicit user call
     */
    public function report(TypeCheck $check, SourceRef $source, ?State $state = null): void
    {
        if ($check->coercion !== null) {
            $this->context->frontier('UNSUPPORTED_LANGUAGE_FEATURE', $source, $check->operation, [$check->coercion]);
            if ($state !== null) {
                (new Havoc())->call($state, [$check->coercion], [], 'UNSUPPORTED_LANGUAGE_FEATURE');
            }
        }
        if ($check->diagnostic) {
            $this->context->frontier('PHP_WARNING', $source, 'implicit-integer-precision-loss');
        }
    }

    /**
     * Preserves exact scalar types regardless of the order of union arms.
     * @param Term $value Actual input
     * @param list<string> $types Declared union arms
     * @return TypeCheck|null Accepted value or no matching runtime type
     */
    public function matching(Term $value, array $types): ?TypeCheck
    {
        if ($value->kind === 'constant' && in_array(get_debug_type($value->literal), $types, true)) {
            return new TypeCheck($value);
        }
        foreach ($types as $part) {
            if ($this->accepts($value, $part)) {
                return new TypeCheck($part === 'float' && $value->kind === 'constant' && is_int($value->literal) ? Term::constant((float) $value->literal, $value->isSecret()) : $value);
            }
        }
        return null;
    }
}
