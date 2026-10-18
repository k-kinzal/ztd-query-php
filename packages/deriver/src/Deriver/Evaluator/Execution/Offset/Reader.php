<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Offset;

use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\IntegerConversion;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Hierarchy\Builtins;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Reads offsets using the runtime container category and original key.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Reader
{
    /**
     * @param Context $context Captured declaration world and diagnostics
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Reads arrays, string bytes, and scalar offsets without inventing array storage.
     * @param Term $container Evaluated container
     * @param Term|null $key Original offset
     * @param Instruction $instruction Read origin
     * @param State $state Reference storage
     * @param bool $silent Whether missing storage suppresses diagnostics
     * @return Term Read value, throwable, or explicit unresolved operation
     */
    public function read(Term $container, ?Term $key, Instruction $instruction, State $state, bool $silent = false): Term
    {
        if (($instruction->attributes['destructure'] ?? false) === true && in_array($container->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Uninitialized], true)) {
            return Term::constant(null);
        }
        if ($container->kind === \Deriver\Deriver\Evaluator\TermKind::Array || $container->kind !== \Deriver\Deriver\Evaluator\TermKind::Uninitialized && ($container->attributes['type'] ?? '') === 'array') {
            return $this->array($container, $key, $instruction, $state, $silent);
        }
        if ($container->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($container->literal)) {
            return (new Strings($this->context))->read($container, $key, $instruction, $silent);
        }
        if ($container->kind === \Deriver\Deriver\Evaluator\TermKind::Constant || $container->kind === \Deriver\Deriver\Evaluator\TermKind::Uninitialized) {
            if (!$silent) {
                (new Strings($this->context))->warning($instruction);
            }
            return Term::constant(null);
        }
        if ($this->plainObject($container)) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error');
        }
        return Term::opaque('OFFSET_OPERATION', dependencies: [$container, ...($key === null ? [] : [$key])]);
    }

    /**
     * Selects an array entry after checking target key conversion.
     * @param Term $container Array shape
     * @param Term|null $key Evaluated key
     * @param Instruction $instruction Read origin
     * @param State $state Shared reference memory
     * @param bool $silent Whether absence is tested silently
     * @return Term Entry, absence, symbolic selection, or TypeError
     */
    public function array(Term $container, ?Term $key, Instruction $instruction, State $state, bool $silent): Term
    {
        if ($key === null) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error');
        }
        $normalized = $this->key($key, $instruction);
        if ($normalized->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
            return $normalized;
        }
        if ($normalized->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !is_int($normalized->literal) && !is_string($normalized->literal)) {
            $types = explode('|', (string) ($key->attributes['type'] ?? 'mixed'));
            return new Term(\Deriver\Deriver\Evaluator\TermKind::ArrayRead, operands: [$container, $normalized], attributes: ['silent' => $silent, 'mayRejectKey' => array_diff($types, ['int', 'string', 'float', 'bool', 'null', 'true', 'false']) !== []]);
        }
        $head = (new Arrays())->head($container);
        if ($head !== null && is_int($normalized->literal) && array_key_exists($normalized->literal, $head->operands)) {
            return $state->memory->element($head, $normalized->literal);
        }
        if ($container->kind !== \Deriver\Deriver\Evaluator\TermKind::Array) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::ArrayRead, operands: [$container, $normalized], attributes: ['silent' => $silent, 'type' => $container->attributes['elementType'] ?? 'mixed']);
        }
        $value = $state->memory->element($container, $normalized->literal);
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Uninitialized && !$silent) {
            (new Strings($this->context))->warning($instruction);
            return Term::constant(null);
        }
        return $value;
    }

    /**
     * Converts an array key and records lossy floating-point conversions.
     * @param Term $key Raw key
     * @param Instruction $instruction Origin
     * @return Term Target key or invalid-key error
     */
    public function key(Term $key, Instruction $instruction): Term
    {
        if ($key->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_float($key->literal) && (new IntegerConversion())->warning($key->literal)) {
            (new Strings($this->context))->warning($instruction);
        }
        return (new Operations())->arrayKey($key);
    }

    /**
     * Checks whether an object definitely has no offset access protocol.
     * @param Term $value Runtime container
     * @return bool Whether using offset syntax must throw
     */
    public function plainObject(Term $value): bool
    {
        if (in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true)) {
            return true;
        }
        $class = $value->attributes['class'] ?? '';
        if ($value->kind !== \Deriver\Deriver\Evaluator\TermKind::Object || !is_string($class) || $class === '') {
            return false;
        }
        $known = isset($this->context->program->classes()[strtolower($class)]) || (new Builtins())->name($class) !== null;
        return $known && !(new Dispatch($this->context->program))->subtype($class, 'ArrayAccess');
    }
}
