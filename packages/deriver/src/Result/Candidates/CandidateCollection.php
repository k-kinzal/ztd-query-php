<?php

declare(strict_types=1);

namespace Deriver\Result\Candidates;

use ArrayIterator;
use Countable;
use Deriver\Reference\ResultRef;
use Deriver\Result\Serialization\Candidates\Encoding;
use Deriver\Result\Statistics;
use IteratorAggregate;
use JsonException;
use JsonSerializable;
use stdClass;

/**
 * The candidate set for one observation; normal JSON is a candidate array.
 * @implements IteratorAggregate<int, Candidate>
 * @example Exporting an empty candidate set
 *     (new \Deriver\Result\Candidates\CandidateCollection([], new \Deriver\Reference\ResultRef('r')))->toJson() // => '[]'
 * @visibility public
 */
final class CandidateCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param list<Candidate> $candidates
     */
    public function __construct(
        public readonly array $candidates,
        public readonly ResultRef $reference,
        public readonly Statistics $statistics = new Statistics(),
        public readonly bool $interrupted = false,
    ) {
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
