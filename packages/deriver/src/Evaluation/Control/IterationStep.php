<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Memory\LiveArray;
use Deriver\Memory\Location;
use Deriver\Model\Builtin\TypePredicates;
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
        $head = (new Arrays())->head($array);
        if ($instruction->operation === 'iterate') {
            return $this->advance($id, new IteratorCursor($array, $cursor->location, $cursor->position + 1), $head, $state);
        }
        $key = $this->key($id, $cursor, $array, $head, $state);
        if ($instruction->operation === 'iterator-address' && $cursor->location !== null) {
            $location = $key === null ? $this->unknownElement($state, $cursor->location, $array) : new Location($cursor->location->root, [...$cursor->location->path, $key]);
            $state->addresses[$instruction->result] = $location;
            return new Term('location', $location->root);
        }
        if ($key === null) {
            return Term::opaque('UNKNOWN_ITERABLE');
        }
        if ($instruction->operation === 'iterator-key') {
            return Term::constant($key, $array->secret);
        }
        return $state->memory->element($head ?? $array, $key, $head !== null && $array->isSecret());
    }

    /**
     * Selects known suffix values at their possible iteration positions, retaining an unknown-prefix path.
     * @param Instruction $instruction Iterator value read
     * @param State $state Iteration path
     * @return list<State>|null Refined suffix candidates or ordinary cursor evaluation
     */
    public function candidates(Instruction $instruction, State $state): ?array
    {
        $iterator = $state->value($instruction->operands[0] ?? '');
        $cursor = is_string($iterator->literal) ? ($state->iterators[$iterator->literal] ?? null) : null;
        $split = $cursor === null || $cursor->location !== null ? null : (new Arrays())->tail($cursor->array);
        if ($split === null) {
            return null;
        }
        [$prefix, $suffix] = $split;
        $minimum = count((new Arrays())->head($prefix)->operands ?? []);
        if ($cursor->position < $minimum) {
            return null;
        }
        $results = [];
        $remaining = $state->fork();
        $count = new Term('intrinsic', 'count', [$prefix], ['type' => 'int']);
        foreach (array_values($suffix->operands) as $position => $value) {
            if ($cursor->position < $position || count($results) >= $this->context->query->budget()->partitions - 1) {
                break;
            }
            if ($cursor->position - $position < $minimum) {
                continue;
            }
            $test = new Term('binary', '===', [$count, Term::constant($cursor->position - $position)], ['type' => 'bool']);
            $path = $remaining->fork();
            if ((new Constraints($this->context))->assume($path, $test, true)) {
                $resolved = $path->memory->dereference($value);
                $path->registers[$instruction->result] = $cursor->array->isSecret() ? new Term($resolved->kind, $resolved->literal, $resolved->operands, $resolved->attributes, true) : $resolved;
                $results[] = $path;
            }
            if (!(new Constraints($this->context))->assume($remaining, $test, false)) {
                return $results;
            }
        }
        $remaining->registers[$instruction->result] = Term::opaque('UNKNOWN_ITERABLE', dependencies: [$prefix]);
        $results[] = $remaining;
        return $results;
    }

    /**
     * Selects the current key only where its position is certain: tracked buckets of a closed live array, or known leading entries of a snapshot.
     * @param string $id Iterator identity
     * @param IteratorCursor $cursor Current cursor
     * @param Term $array Iterated value
     * @param Term|null $head Known leading entries of a symbolic merge
     * @param State $state Current path
     * @return int|string|null Current key, or null when it is unknown
     */
    public function key(string $id, IteratorCursor $cursor, Term $array, ?Term $head, State $state): int|string|null
    {
        $closed = $array->kind === 'array' && ($array->attributes['open'] ?? false) === false;
        if ($cursor->location !== null) {
            return $closed ? $state->memory->liveArrays[$id]->current ?? null : null;
        }
        $entries = $head ?? $array;
        if ($entries->kind !== 'array' || array_filter($entries->operands, static fn (Term $entry): bool => ($entry->attributes['maybeUninitialized'] ?? false) === true) !== []) {
            return null;
        }
        return array_keys($entries->operands)[$cursor->position] ?? null;
    }

    /**
     * Addresses an unknown entry of by-reference iterated storage, so every write through it invalidates that storage.
     * @param State $state Current path
     * @param Location $location Pinned iterated variable
     * @param Term $subject Iterated value
     * @return Location Unknown element of the array storage, or of the object's property storage
     */
    public function unknownElement(State $state, Location $location, Term $subject): Location
    {
        $array = new Location($location->root, $location->path, unknown: true);
        if ((new TypePredicates())->apply('is_array', $subject)->literal === true) {
            return $array;
        }
        $seen = [];
        (new Havoc())->reachable($state, $subject, 'UNKNOWN_REFERENCE', $seen);
        if ($subject->kind === 'object' || (new TypePredicates())->apply('is_object', $subject)->literal === true) {
            return is_string($subject->literal) ? new Location('object:' . $subject->literal, unknown: true) : $array;
        }
        $state->memory->write($array, Term::opaque('UNKNOWN_REFERENCE', dependencies: [$subject]));
        return is_string($subject->literal) && isset($state->memory->cells['object:' . $subject->literal]) ? new Location('object:' . $subject->literal, unknown: true) : $array;
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
            if (isset($state->memory->liveArrays[$id])) {
                $state->memory->liveArrays[$id] = new LiveArray($state->memory->liveArrays[$id]->location, []);
            }
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
        $array = $location === null ? $state->value($register) : $this->referenced($instruction, $state, $location);
        if ($this->iterable($array) === false) {
            $this->context->frontier('PHP_WARNING', $instruction->source, 'foreach-non-iterable');
            $state->iterators[$id] = new IteratorCursor(new Term('array', attributes: ['open' => false], secret: $array->isSecret()));
            return new Term('iterator', $id);
        }
        if ($location !== null) {
            $location = new Location($state->memory->reference($location));
            $array = $state->memory->read($location);
            $state->memory->liveArrays[$id] = new LiveArray($location, $array->kind === 'array' ? array_keys($array->operands) : []);
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
