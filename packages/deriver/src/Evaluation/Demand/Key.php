<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Demand;

/**
 * Names one computation in a snapshot, projection, state, and constraint partition.
 * @visibility root
 */
final class Key
{
    /**
     * @param string $snapshot Captured declaration world
     * @param string $owner Callable or expression identity
     * @param string $point Program point
     * @param string $kind value, location, dispatch, receiver-type, or effect
     * @param string $projection Demanded result projection
     * @param string $context Relevant abstract input and bounded call history
     * @param string $state Relevant storage identity
     * @param string $partition Correlated entry constraints
     * @param int $epoch Semantic refinement generation
     */
    public function __construct(
        public readonly string $snapshot,
        public readonly string $owner,
        public readonly string $point,
        public readonly string $kind,
        public readonly string $projection,
        public readonly string $context,
        public readonly string $state,
        public readonly string $partition,
        public readonly int $epoch = 0,
    ) {
    }

    /**
     * Produces an unambiguous deterministic identifier for the complete demand.
     * @return string Content identity
     */
    public function id(): string
    {
        return hash('sha256', serialize([$this->snapshot, $this->owner, $this->point, $this->kind, $this->projection, $this->context, $this->state, $this->partition, $this->epoch]));
    }
}
