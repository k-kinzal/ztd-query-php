<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Memory\LiveArray;
use Deriver\Memory\Location;
use Deriver\Model\Builtin\TypePredicates;
use Deriver\Value\Term;

/**
 * Evaluates finite foreach cursors and preserves referenced element addresses.
 * @visibility root
 */
final class IterationStep
{
    /**
     * @param Context $context Query context
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Executes one cursor instruction.
     * @param Instruction $instruction Iterator operation
     * @param State $state Current path
     * @return Term Result
     */
    public function evaluate(Instruction $instruction, State $state): Term
    {
        if ($instruction->operation === 'iterator') {
            return $this->initialize($instruction, $state);
        }
        $iterator = $state->value($instruction->operands[0] ?? '');
        $id = is_string($iterator->literal) ? $iterator->literal : '';
        if ($instruction->operation === 'iterator-release') {
            unset($state->memory->liveArrays[$id], $state->iterators[$id]);
            return Term::constant(null);
        }
        $cursor = $state->iterators[$id] ?? new IteratorCursor(Term::opaque('UNKNOWN_ITERABLE'));
        $array = $cursor->location === null ? $cursor->array : $state->memory->read($cursor->location);
        if ($instruction->operation === 'iterate') {
            $cursor = new IteratorCursor($array, $cursor->location, $cursor->position + 1);
            $state->iterators[$id] = $cursor;
            if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
                return new Term('external', $id . ':has-next:' . $cursor->position, attributes: ['type' => 'bool'], secret: $array->secret);
            }
            if (isset($state->memory->liveArrays[$id])) {
                $state->memory->liveArrays[$id] = $state->memory->liveArrays[$id]->advance();
                return Term::constant($state->memory->liveArrays[$id]->current !== null, $array->secret);
            }
            return Term::constant($cursor->position < count($array->operands), $array->secret);
        }
        $key = isset($state->memory->liveArrays[$id]) ? $state->memory->liveArrays[$id]->current : (array_keys($array->operands)[$cursor->position] ?? null);
        if ($key === null) {
            return Term::opaque('UNKNOWN_ITERABLE');
        }
        if ($instruction->operation === 'iterator-key') {
            return Term::constant($key, $array->secret);
        }
        if ($instruction->operation === 'iterator-address' && $cursor->location !== null) {
            $location = new Location($cursor->location->root, [...$cursor->location->path, $key]);
            $state->addresses[$instruction->result] = $location;
            return new Term('location', $location->root);
        }
        return $state->memory->element($array, $key);
    }

    /**
     * Pins a live reference array independently of subsequent local variable rebinding.
     * @param Instruction $instruction Foreach entry
     * @param State $state Current path
     * @return Term Iterator identity
     */
    public function initialize(Instruction $instruction, State $state): Term
    {
        $id = $state->memory->fresh('iterator');
        $register = $instruction->operands[0] ?? '';
        $location = ($instruction->attributes['byReference'] ?? false) === true ? ($state->addresses[$register] ?? null) : null;
        $array = $location === null ? $state->value($register) : $this->referenced($instruction, $state, $location);
        if ($this->iterable($array) === false) {
            $this->context->frontier('PHP_WARNING', $instruction->source, 'foreach-non-iterable');
            $state->iterators[$id] = new IteratorCursor(new Term('array', attributes: ['open' => false], secret: $array->isSecret()));
            return new Term('iterator', $id);
        }
        if ($location !== null) {
            $location = new Location($state->memory->reference($location));
            $array = $state->memory->read($location);
            $state->memory->liveArrays[$id] = new LiveArray($location, array_keys($array->operands));
        }
        $state->iterators[$id] = new IteratorCursor($array, $location);
        return new Term('iterator', $id);
    }

    /**
     * Reads a by-reference subject as PHP's write fetch does, without defining an undefined plain variable.
     * @param Instruction $instruction Foreach entry
     * @param State $state Current path
     * @param Location $location Iterated storage
     * @return Term Current subject value
     */
    public function referenced(Instruction $instruction, State $state, Location $location): Term
    {
        $value = $state->memory->read($location);
        if ($value->kind !== 'uninitialized') {
            return $value;
        }
        if ($location->path === [] && !$location->unknown) {
            return (new MemoryStep($this->context))->uninitialized($instruction, $location, $state);
        }
        $state->memory->reference($location);
        return $state->memory->read($location);
    }

    /**
     * Decides whether foreach accepts the subject; PHP warns and skips the body for scalars and null.
     * @param Term $subject Iterated value
     * @return bool|null Whether the subject is an array or object, or null when its type is not determined
     */
    public function iterable(Term $subject): ?bool
    {
        if ($subject->kind === 'constant') {
            return false;
        }
        $predicates = new TypePredicates();
        $array = $predicates->apply('is_array', $subject)->literal;
        $object = $predicates->apply('is_object', $subject)->literal;
        if ($array === true || $object === true) {
            return true;
        }
        return $array === false && $object === false ? false : null;
    }
}
