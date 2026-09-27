<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Arrays;
use Deriver\Value\Increment;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Transfers reads, writes, aliases, and static/global bindings.
 * @visibility root
 */
final class MemoryStep
{
    /**
     * @param Context $context Query context
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Executes one storage instruction.
     * @param CallableGraph $callable Current callable
     * @param Instruction $instruction Storage operation
     * @param State $state Path-specific memory
     * @return Term Evaluated value
     */
    public function evaluate(CallableGraph $callable, Instruction $instruction, State $state): Term
    {
        $op = $instruction->operation;
        if ($op === 'local' && str_starts_with($callable->symbol, 'script:')) {
            $root = 'global:' . $instruction->name;
            $state->memory->cells[$root] ??= $state->memory->read($state->local($instruction->name));
            $state->locals[$instruction->name] = new Location($root);
        }
        if (in_array($op, ['local', 'element-address', 'field-address', 'static-address', 'unsupported-address', 'returned-address'], true)) {
            return $this->prepareAddress($instruction, $state);
        }
        $address = $state->addresses[$instruction->operands[0] ?? ''] ?? new Location('unknown', unknown: true);
        if ($op === 'read' || $op === 'read-silent') {
            $value = $state->memory->read($address);
            if ($value->kind === 'uninitialized' && $op === 'read') {
                return $this->uninitialized($instruction, $address, $state);
            }
            return $value;
        }
        if ($op === 'write') {
            $value = $state->value($instruction->operands[1] ?? '');
            $state->memory->write($address, $value);
            return $value;
        }
        if ($op === 'alias') {
            $source = $state->addresses[$instruction->operands[1] ?? ''] ?? new Location('unknown', unknown: true);
            $state->alias($address, $source);
            return $state->memory->read($source);
        }
        if ($op === 'reference') {
            return new Term('cell', $state->memory->reference($address));
        }
        if ($op === 'increment') {
            $before = $state->memory->read($address);
            $after = $this->incremented($before, $instruction);
            if ($after->kind === 'throwable') {
                return $after;
            }
            $state->memory->write($address, $after);
            return ($instruction->attributes['post'] ?? false) === true ? $before : $after;
        }
        return $this->binding($callable, $instruction, $state, $address);
    }

    /**
     * Stores a resolved address only after validating an array append index.
     * @param Instruction $instruction Address creation
     * @param State $state Memory and address registers
     * @return Term Address token or a target append error
     */
    public function prepareAddress(Instruction $instruction, State $state): Term
    {
        if ($instruction->operation === 'element-address') {
            $key = ($instruction->operands[1] ?? '') === '' ? null : $state->value($instruction->operands[1]);
            $state->offsets[$instruction->result] = new Address($instruction->operands[0], $key);
        }
        $location = $this->address($instruction, $state);
        $state->addresses[$instruction->result] = $location;
        return new Term('location', $location->root);
    }

    /**
     * Evaluates target increment semantics and records warnings at their source location.
     * @param Term $before Value before mutation
     * @param Instruction $instruction Increment or decrement operation
     * @return Term New value or explicit throwable
     */
    public function incremented(Term $before, Instruction $instruction): Term
    {
        $delta = (int) ($instruction->attributes['delta'] ?? 1);
        $operation = new Increment();
        if ($operation->warning($before, $delta)) {
            $this->context->frontier('PHP_WARNING', $instruction->source, 'increment-diagnostic');
        }
        return $operation->apply($before, $delta);
    }

    /**
     * Computes an address, retaining dynamic-key uncertainty.
     * @param Instruction $instruction Address operation
     * @param State $state Current memory
     * @return Location Address
     */
    public function address(Instruction $instruction, State $state): Location
    {
        if ($instruction->operation === 'returned-address') {
            return $this->returnedAddress($instruction, $state);
        }
        if ($instruction->operation === 'local') {
            return $state->local($instruction->name);
        }
        if ($instruction->operation === 'element-address') {
            $parent = $state->addresses[$instruction->operands[0] ?? ''] ?? new Location('unknown', unknown: true);
            $property = $state->properties[$instruction->operands[0] ?? ''] ?? null;
            if ($property !== null) {
                $state->properties[$instruction->result] = $property;
            }
            $register = $instruction->operands[1] ?? '';
            $key = $register === '' ? (new Arrays())->appendKey($state->memory->read($parent)) : (new Operations())->arrayKey($state->value($register));
            if ($key->kind !== 'constant' || (!is_int($key->literal) && !is_string($key->literal))) {
                return new Location($parent->root, $parent->path, unknown: true);
            }
            return new Location($parent->root, [...$parent->path, $key->literal]);
        }
        if ($instruction->operation === 'field-address' || $instruction->operation === 'static-address') {
            return (new ObjectAccess($this->context))->address($instruction, $state);
        }
        $this->context->frontier('UNSUPPORTED_LANGUAGE_FEATURE', $instruction->source, $instruction->name);
        return new Location('unknown', unknown: true);
    }

    /**
     * Distinguishes uninitialized properties from ordinary undefined variables.
     * @param Instruction $instruction Read operation
     * @param Location $address Address
     * @param State $state Current path
     * @return Term PHP read result or throwable
     */
    public function uninitialized(Instruction $instruction, Location $address, State $state): Term
    {
        if ($address->local === 'this' || ($state->memory->read($address)->attributes['type'] ?? 'mixed') !== 'mixed') {
            return new Term('throwable', 'Error');
        }
        $this->context->frontier('PHP_WARNING', $instruction->source, 'uninitialized-read');
        return Term::constant(null);
    }

    /**
     * Applies unset, global, and function-static bindings.
     * @param CallableGraph $callable Current callable
     * @param Instruction $instruction Binding operation
     * @param State $state Current path
     * @param Location $address Local address
     * @return Term Result of the effect
     */
    public function binding(CallableGraph $callable, Instruction $instruction, State $state, Location $address): Term
    {
        if ($instruction->operation === 'static-initialized') {
            $root = 'static:' . $callable->symbol . ':' . $address->local;
            if ($state->memory->unknownShared !== null) {
                $state->memory->cells[$root] ??= Term::opaque($state->memory->unknownShared);
            }
            $present = array_key_exists($root, $state->memory->cells);
            if ($present) {
                $state->locals[$address->local] = new Location($root);
            }
            return Term::constant($present);
        }
        if ($instruction->operation === 'unset') {
            if ($address->local !== '' && $address->path === []) {
                unset($state->locals[$address->local]);
            } else {
                $state->memory->remove($address);
            }
        } elseif ($instruction->operation === 'global') {
            $root = 'global:' . $address->local;
            $state->memory->cells[$root] ??= new Term('external', $root, attributes: ['type' => 'mixed']);
            $state->locals[$address->local] = new Location($root);
        } elseif ($instruction->operation === 'static-local') {
            $root = 'static:' . $callable->symbol . ':' . $address->local;
            $state->memory->cells[$root] ??= $state->value($instruction->operands[1] ?? '');
            $state->locals[$address->local] = new Location($root);
        }
        return Term::constant(null);
    }
    /**
     * Resolves a returned reference or allocates permitted temporary iteration storage.
     * @param Instruction $instruction Returned-address operation and diagnostic policy
     * @param State $state Evaluated return register and reference memory
     * @return Location Shared cell, temporary storage, or an unresolved reference
     */
    public function returnedAddress(Instruction $instruction, State $state): Location
    {
        $value = $state->registers[$instruction->operands[0] ?? ''] ?? Term::opaque('INVALID_REFERENCE');
        if ($value->kind === 'cell' && is_string($value->literal)) {
            return new Location($value->literal);
        }
        if (($instruction->attributes['temporary-reference'] ?? false) === true) {
            if (($instruction->attributes['temporary-warning'] ?? true) === true) {
                $this->context->frontier('PHP_WARNING', $instruction->source, 'non-referenceable-return');
            }
            return $state->memory->allocate($value);
        }
        return new Location('invalid-reference', unknown: true);
    }
}
