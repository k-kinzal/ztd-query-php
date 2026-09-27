<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Offset;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Value\IntegerConversion;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Reads offsets using the runtime container category and original key.
 * @visibility root
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
        if (($instruction->attributes['destructure'] ?? false) === true && in_array($container->kind, ['constant', 'uninitialized'], true)) {
            return Term::constant(null, $container->isSecret());
        }
        if ($container->kind === 'array') {
            return $this->array($container, $key, $instruction, $state, $silent);
        }
        if ($container->kind === 'constant' && is_string($container->literal)) {
            return (new Strings($this->context))->read($container, $key, $instruction, $silent);
        }
        if ($container->kind === 'constant' || $container->kind === 'uninitialized') {
            if (!$silent) {
                (new Strings($this->context))->warning($instruction);
            }
            return Term::constant(null);
        }
        if ($this->plainObject($container)) {
            return new Term('throwable', 'Error');
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
            return new Term('throwable', 'Error');
        }
        $normalized = $this->key($key, $instruction);
        if ($normalized->kind === 'throwable') {
            return $normalized;
        }
        if ($normalized->kind !== 'constant' || !is_int($normalized->literal) && !is_string($normalized->literal)) {
            return Term::opaque('OFFSET_OPERATION', dependencies: [$container, $key]);
        }
        $value = $state->memory->element($container, $normalized->literal, $key->isSecret());
        if ($value->kind === 'uninitialized' && !$silent) {
            (new Strings($this->context))->warning($instruction);
            return Term::constant(null, $value->isSecret());
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
        if ($key->kind === 'constant' && is_float($key->literal) && (new IntegerConversion())->warning($key->literal)) {
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
        if (in_array($value->kind, ['closure', 'enum'], true)) {
            return true;
        }
        $class = $value->attributes['class'] ?? '';
        if ($value->kind !== 'object' || !is_string($class) || $class === '') {
            return false;
        }
        $known = isset($this->context->program->classes()[strtolower($class)]) || (new Builtins())->name($class) !== null;
        return $known && !(new Dispatch($this->context->program))->subtype($class, 'ArrayAccess');
    }
}
