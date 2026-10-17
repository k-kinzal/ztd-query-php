<?php

declare(strict_types=1);

namespace Deriver\Result;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Deriver\Reference\ResultRef;
use Deriver\Result\Serialization\Candidates\Encoding;
use IteratorAggregate;
use JsonException;
use JsonSerializable;
use OutOfBoundsException;
use RuntimeException;
use stdClass;

/**
 * The candidate set for one observation; normal JSON is a candidate array.
 * @implements ArrayAccess<int, Candidate>
 * @implements IteratorAggregate<int, Candidate>
 * @example Exporting an empty candidate set
 *     (new \Deriver\Result\CandidateCollection([], new \Deriver\Reference\ResultRef('r')))->toJson() // => '[]'
 * @visibility public
 */
final class CandidateCollection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param list<Candidate> $candidates
     */
    public function __construct(
        private readonly array $candidates,
        public readonly ResultRef $reference,
        public readonly Statistics $statistics = new Statistics(),
        public readonly bool $interrupted = false,
    ) {
    }

    /**
     * Tests whether an integer offset identifies a candidate.
     * @param mixed $offset Offset supplied through ArrayAccess
     */
    public function offsetExists(mixed $offset): bool
    {
        return is_int($offset) && isset($this->candidates[$offset]);
    }

    /**
     * Returns a candidate by its zero-based integer offset.
     * @param mixed $offset Offset supplied through ArrayAccess
     * @throws OutOfBoundsException If the offset is not an integer or has no candidate
     */
    public function offsetGet(mixed $offset): Candidate
    {
        if (!is_int($offset) || !isset($this->candidates[$offset])) {
            throw new OutOfBoundsException('No candidate at the supplied offset; use an existing integer offset.');
        }
        return $this->candidates[$offset];
    }

    /**
     * @throws RuntimeException Candidate collections cannot be changed
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException('CandidateCollection is read-only.');
    }

    /**
     * @throws RuntimeException Candidate collections cannot be changed
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('CandidateCollection is read-only.');
    }

    /**
     * Returns the number of concrete and partial candidate records.
     */
    public function count(): int
    {
        return count($this->candidates);
    }

    /**

     * @return ArrayIterator<int, Candidate>

     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->candidates);
    }

    /**

     * Filters derivations by a caller without choosing between remaining values.

     */
    public function forCaller(string $caller): self
    {
        $candidates = [];
        foreach ($this->candidates as $candidate) {
            $evidence = array_values(array_filter($candidate->evidence, static fn ($proof): bool => $proof->matchesCaller($caller)));
            if ($evidence !== []) {
                $candidates[] = new Candidate($candidate->term, $evidence);
            }
        }
        return new self($candidates, $this->reference, $this->statistics, $this->interrupted);
    }

    /**

     * @return list<stdClass>

     */
    public function jsonSerialize(): array
    {
        return (new Encoding())->encode($this);
    }

    /**

     * @throws JsonException

     */
    public function toJson(bool $includeSecrets = false): string
    {
        return json_encode((new Encoding())->encode($this, $includeSecrets), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
