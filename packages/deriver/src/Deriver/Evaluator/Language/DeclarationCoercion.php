<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Language;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Scalar\NumericString;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Applies declaration constraints directly to demanded values, without execution state.
 * @visibility parent
 */
final class DeclarationCoercion
{
    /**
     * Applies a declaration type only to demanded value candidates.
     */
    public function check(Evaluator $engine, Term $value, string $type, bool $strict, string $class = ''): Term
    {
        if (in_array($type, ['mixed', '', 'void'], true) || $value->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
            return $value;
        }
        $types = array_map(fn (string $part): string => $engine->context->index->className($part, $class), explode('|', $type));
        return (new Choices($engine->context->provenance))->apply('type:' . $type, [$value], fn (array $values): Term => $this->coerce($engine, $values[0], $types, $strict), $engine->context->budget->partitions);
    }

    /**

     * @param list<string> $types

     */
    public function coerce(Evaluator $engine, Term $value, array $types, bool $strict): Term
    {
        $actual = $value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant ? get_debug_type($value->literal) : (string) ($value->attributes['type'] ?? $value->kind->value);
        if (in_array($actual, $types, true) || in_array('mixed', $types, true)) {
            return $value;
        }
        foreach ($types as $type) {
            $accepted = $this->accept($engine, $value, $type);
            if ($accepted !== null) {
                return $accepted;
            }
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && !$strict && $value->literal !== null) {
            foreach (['int', 'float', 'string', 'bool'] as $type) {
                if (in_array($type, $types, true) && (!in_array($type, ['int', 'float'], true) || !is_string($value->literal) || (new NumericString())->parse($value->literal) !== null)) {
                    return (new Operations($engine->context->configuration->target->floatPrecision))->cast($type, $value);
                }
            }
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant || $value->kind === \Deriver\Deriver\Evaluator\TermKind::Array) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError', [$value]);
        }
        return new Term(\Deriver\Deriver\Evaluator\TermKind::TypeRefinement, implode('|', $types), [$value], ['type' => implode('|', $types), 'reason' => 'UNKNOWN_TYPE_RELATION']);
    }
    /**
     * Tests a single exact type alternative without converting unknown class relationships.
     */
    public function accept(Evaluator $engine, Term $value, string $type): ?Term
    {
        if ($type === 'float' && is_int($value->literal) && $value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return Term::constant((float) $value->literal);
        }
        if ($type === 'null' && $value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && $value->literal === null || $type === 'true' && $value->literal === true || $type === 'false' && $value->literal === false) {
            return $value;
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Array && in_array($type, ['array', 'iterable'], true) || in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Enum, \Deriver\Deriver\Evaluator\TermKind::Closure], true) && $type === 'object') {
            return $value;
        }
        if ($type === 'callable' || $type === 'Closure') {
            return $this->callable($engine, $value, $type);
        }
        if (in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Enum], true) && (new Dispatch($engine->context->index->program))->subtype((string) ($value->attributes['class'] ?? ''), $type)) {
            return $value;
        }
        return null;
    }
    /**
     * Recognizes captured callable shapes without accepting arbitrary two-element arrays.
     */
    public function callable(Evaluator $engine, Term $value, string $type): ?Term
    {
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Closure) {
            return $value;
        }
        if ($type === 'Closure') {
            return null;
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($value->literal)) {
            return $engine->context->index->graph($value->literal) !== null || (new \Deriver\Deriver\Evaluator\Builtin\Library())->evaluator($value->literal) !== null ? $value : null;
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Object && $engine->context->index->graph((string) ($value->attributes['class'] ?? '') . '::__invoke') !== null) {
            return $value;
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Array && array_keys($value->operands) === [0, 1]) {
            $receiver = $value->operands[0];
            $method = $value->operands[1];
            $class = $receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Object ? ($receiver->attributes['class'] ?? '') : $receiver->literal;
            if (is_string($class) && is_string($method->literal) && $engine->context->index->graph($class . '::' . $method->literal) !== null) {
                return $value;
            }
            return new Term(\Deriver\Deriver\Evaluator\TermKind::TypeRefinement, 'callable', [$value], ['type' => 'callable', 'reason' => 'UNRESOLVED_DISPATCH']);
        }
        return null;
    }

}
