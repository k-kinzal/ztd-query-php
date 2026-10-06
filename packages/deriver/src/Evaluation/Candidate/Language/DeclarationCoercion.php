<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Language;

use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Applies declaration constraints directly to demanded values, without execution state.
 * @visibility root
 */
final class DeclarationCoercion
{
    /**
     * Applies a declaration type only to demanded value candidates.
     */
    public function check(Derivation $engine, Term $value, string $type, bool $strict, string $class = ''): Term
    {
        if (in_array($type, ['mixed', '', 'void'], true) || $value->kind === 'throwable') {
            return $value;
        }
        $types = array_map(fn (string $part): string => $engine->context->index->className($part, $class), explode('|', $type));
        return (new Choices())->apply('type:' . $type, [$value], fn (array $values): Term => $this->coerce($engine, $values[0], $types, $strict), $engine->context->budget->partitions);
    }

    /**

     * @param list<string> $types

     */
    public function coerce(Derivation $engine, Term $value, array $types, bool $strict): Term
    {
        $actual = $value->kind === 'constant' ? get_debug_type($value->literal) : (string) ($value->attributes['type'] ?? $value->kind);
        if (in_array($actual, $types, true) || in_array('mixed', $types, true)) {
            return $value;
        }
        foreach ($types as $type) {
            $accepted = $this->accept($engine, $value, $type);
            if ($accepted !== null) {
                return $accepted;
            }
        }
        if ($value->kind === 'constant' && !$strict && $value->literal !== null) {
            foreach (['int', 'float', 'string', 'bool'] as $type) {
                if (in_array($type, $types, true) && (!in_array($type, ['int', 'float'], true) || !is_string($value->literal) || (new NumericString())->parse($value->literal) !== null)) {
                    return (new Operations($engine->context->configuration->target->floatPrecision))->cast($type, $value);
                }
            }
        }
        if ($value->kind === 'constant' || $value->kind === 'array') {
            return new Term('throwable', 'TypeError', [$value]);
        }
        return new Term('type-refinement', implode('|', $types), [$value], ['type' => implode('|', $types), 'reason' => 'UNKNOWN_TYPE_RELATION']);
    }
    /**
     * Tests a single exact type alternative without converting unknown class relationships.
     */
    public function accept(Derivation $engine, Term $value, string $type): ?Term
    {
        if ($type === 'float' && is_int($value->literal) && $value->kind === 'constant') {
            return Term::constant((float) $value->literal, $value->isSecret());
        }
        if ($type === 'null' && $value->kind === 'constant' && $value->literal === null || $type === 'true' && $value->literal === true || $type === 'false' && $value->literal === false) {
            return $value;
        }
        if ($value->kind === 'array' && in_array($type, ['array', 'iterable'], true) || in_array($value->kind, ['object', 'enum', 'closure'], true) && $type === 'object') {
            return $value;
        }
        if ($type === 'callable' || $type === 'Closure') {
            return $this->callable($engine, $value, $type);
        }
        if ($value->kind === 'object' && (new Dispatch($engine->context->index->program))->subtype((string) ($value->attributes['class'] ?? ''), $type)) {
            return $value;
        }
        return null;
    }
    /**
     * Recognizes captured callable shapes without accepting arbitrary two-element arrays.
     */
    public function callable(Derivation $engine, Term $value, string $type): ?Term
    {
        if ($value->kind === 'closure') {
            return $value;
        }
        if ($type === 'Closure') {
            return null;
        }
        if ($value->kind === 'constant' && is_string($value->literal)) {
            return $engine->context->index->graph($value->literal) !== null || (new \Deriver\Model\Builtin\Library())->model($value->literal) !== null ? $value : null;
        }
        if ($value->kind === 'object' && $engine->context->index->graph((string) ($value->attributes['class'] ?? '') . '::__invoke') !== null) {
            return $value;
        }
        if ($value->kind === 'array' && array_keys($value->operands) === [0, 1]) {
            $receiver = $value->operands[0];
            $method = $value->operands[1];
            $class = $receiver->kind === 'object' ? ($receiver->attributes['class'] ?? '') : $receiver->literal;
            if (is_string($class) && is_string($method->literal) && $engine->context->index->graph($class . '::' . $method->literal) !== null) {
                return $value;
            }
            return new Term('type-refinement', 'callable', [$value], ['type' => 'callable', 'reason' => 'UNRESOLVED_DISPATCH']);
        }
        return null;
    }

}
