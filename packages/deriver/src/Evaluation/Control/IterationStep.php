<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Memory\LiveArray;
use Deriver\Memory\Location;
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
        if ($location !== null) {
            $location = new Location($state->memory->reference($location));
        }
        $array = $location === null ? $state->value($register) : $state->memory->read($location);
        if ($location !== null) {
            $state->memory->liveArrays[$id] = new LiveArray($location, array_keys($array->operands));
        }
        $state->iterators[$id] = new IteratorCursor($array, $location);
        return new Term('iterator', $id);
    }
}
