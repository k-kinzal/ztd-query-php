<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Memory\LiveArray;
use Deriver\Memory\Location;
use Deriver\Value\Arrays;
use Deriver\Value\Term;

/**
 * Evaluates finite foreach cursors, known heads of symbolic merges, and referenced element addresses.
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
        $head = $cursor->location === null ? (new Arrays())->head($array) : null;
        if ($instruction->operation === 'iterate') {
            return $this->advance($id, new IteratorCursor($array, $cursor->location, $cursor->position + 1), $head, $state);
        }
        $entries = $head ?? $array;
        $key = isset($state->memory->liveArrays[$id]) ? $state->memory->liveArrays[$id]->current : ($entries->kind === 'array' ? array_keys($entries->operands)[$cursor->position] ?? null : null);
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
        return $state->memory->element($entries, $key, $head !== null && $array->isSecret());
    }

    /**
     * Moves a cursor by one entry and reports whether that entry exists.
     * @param string $id Iterator identity
     * @param IteratorCursor $cursor Cursor at its new position
     * @param Term|null $head Known leading entries of a symbolic merge
     * @param State $state Current path
     * @return Term Entry availability
     */
    public function advance(string $id, IteratorCursor $cursor, ?Term $head, State $state): Term
    {
        $state->iterators[$id] = $cursor;
        $array = $cursor->array;
        if ($head !== null && $cursor->position < count($head->operands)) {
            return Term::constant(true, $array->secret);
        }
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
            return new Term('external', $id . ':has-next:' . $cursor->position, attributes: ['type' => 'bool'], secret: $array->secret);
        }
        if (isset($state->memory->liveArrays[$id])) {
            $state->memory->liveArrays[$id] = $state->memory->liveArrays[$id]->advance();
            return Term::constant($state->memory->liveArrays[$id]->current !== null, $array->secret);
        }
        return Term::constant($cursor->position < count($array->operands), $array->secret);
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
