<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Havoc;
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
        $prepared = $this->prepareStorage($callable, $instruction, $state);
        if ($prepared !== null) {
            return $prepared;
        }
        $address = $state->addresses[$instruction->operands[0] ?? ''] ?? new Location('unknown', unknown: true);
        if ($address->root === 'symbol-table' && !in_array($op, ['read', 'read-silent'], true)) {
            (new Havoc())->symbols($state, 'DYNAMIC_VARIABLE_WRITE');
            $this->context->frontier('DYNAMIC_VARIABLE_WRITE', $instruction->source, $op);
        }
        if ($op === 'read' || $op === 'read-silent') {
            return $this->read($instruction, $address, $state);
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
     * Reads storage, reporting undefined variables and globals that the configuration does not supply.
     * @param Instruction $instruction Read, or a silent read for isset-like operations
     * @param Location $address Read storage
     * @param State $state Current path
     * @return Term Stored value, PHP's undefined-read result, or a throwable
     */
    public function read(Instruction $instruction, Location $address, State $state): Term
    {
        $value = $state->memory->read($address);
        if ($value->kind === 'uninitialized' && $instruction->operation === 'read') {
            return $this->uninitialized($instruction, $address, $state);
        }
        if ($value->kind === 'external' && $value->literal === $address->root && $address->path === [] && str_starts_with($address->root, 'global:')) {
            $this->context->frontier('EXTERNAL_INPUT', $instruction->source, 'global-read', knownDependencies: [$address->root]);
        }
        return $value;
    }

    /**
     * Gives a global first touched after an unexplored write to shared storage an unknown, possibly undefined value.
     * @param State $state Current path
     * @return Term|null Residual of the unexplored write, or null when shared storage is fully known
     */
    public function unknownShared(State $state): ?Term
    {
        $reason = $state->memory->unknownShared;
        return $reason === null ? null : new Term('opaque', $reason, attributes: ['type' => 'mixed', 'dependencyCoverage' => 'partial', 'maybeUninitialized' => true]);
    }

    /**
     * Resolves local and dynamic names before ordinary storage operations.
     * @param CallableGraph $callable Current scope
     * @param Instruction $instruction Address instruction
     * @param State $state Current frame
     * @return Term|null Address token, or an ordinary storage operation
     */
    public function prepareStorage(CallableGraph $callable, Instruction $instruction, State $state): ?Term
    {
        $op = $instruction->operation;
        if ($op === 'dynamic-local') {
            $name = (new Operations($this->context->configuration->target->floatPrecision))->cast('string', $state->value($instruction->operands[0]));
            if ($name->kind === 'constant' && is_string($name->literal)) {
                return $this->evaluate($callable, new Instruction($instruction->id, 'local', $instruction->source, $instruction->result, name: $name->literal), $state);
            }
            $state->addresses[$instruction->result] = new Location('symbol-table', unknown: true);
            return new Term('location', 'symbol-table');
        }
        if ($op === 'local' && (str_starts_with($callable->symbol, 'script:') || in_array($instruction->name, ['_GET', '_POST', '_COOKIE', '_SERVER', '_ENV', '_REQUEST', '_FILES', '_SESSION'], true))) {
            $root = 'global:' . $instruction->name;
            $state->memory->cells[$root] ??= $this->context->configuration->environment[$root] ?? $this->unknownShared($state) ?? $state->memory->read($state->local($instruction->name));
            $state->locals[$instruction->name] = new Location($root);
        }
        if (in_array($op, ['local', 'element-address', 'field-address', 'static-address', 'unsupported-address', 'returned-address'], true)) {
            return $this->prepareAddress($instruction, $state);
        }
        return null;
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
        $this->context->frontier('PHP_WARNING', $instruction->source, 'uninitialized-read', knownDependencies: $this->variable($address));
        return Term::constant(null);
    }

    /**
     * Names a variable as the environment key that supplies it, or as a function-local variable.
     * @param Location $address Read variable storage
     * @return list<string> `global:name` for global storage, `variable:name` for a local, or nothing for other storage
     */
    public function variable(Location $address): array
    {
        if ($address->path !== [] || $address->unknown) {
            return [];
        }
        if (str_starts_with($address->root, 'global:')) {
            return [$address->root];
        }
        return $address->local === '' ? [] : ['variable:' . $address->local];
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
            $this->unset($callable, $state, $address);
        } elseif ($instruction->operation === 'global') {
            $root = 'global:' . $address->local;
            $state->memory->cells[$root] ??= $this->context->configuration->environment[$root] ?? $this->unknownShared($state) ?? new Term('external', $root, attributes: ['type' => 'mixed']);
            $state->locals[$address->local] = new Location($root);
        } elseif ($instruction->operation === 'static-local') {
            $root = 'static:' . $callable->symbol . ':' . $address->local;
            $state->memory->cells[$root] ??= $state->value($instruction->operands[1] ?? '');
            $state->locals[$address->local] = new Location($root);
        }
        return Term::constant(null);
    }
    /**
     * Removes a variable binding or an element; an unset variable stays undefined until the next symbol-table boundary.
     * At script scope the global slot is cleared; values shared by reference live in a separate cell, so other names keep them.
     * @param CallableGraph $callable Current scope
     * @param State $state Current path
     * @param Location $address Unset variable or element
     */
    public function unset(CallableGraph $callable, State $state, Location $address): void
    {
        if ($address->local === '' || $address->path !== []) {
            $state->memory->remove($address);
            return;
        }
        unset($state->locals[$address->local]);
        if (str_starts_with($callable->symbol, 'script:')) {
            $state->memory->cells['global:' . $address->local] = new Term('uninitialized');
        }
        if ($state->unknownLocals !== null) {
            $state->locals[$address->local] = $state->memory->allocate(new Term('uninitialized'));
        }
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
