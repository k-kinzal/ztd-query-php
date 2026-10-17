<?php

declare(strict_types=1);

namespace SqlSemantics\Resolution;

use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * Accumulates the aggregate occurrences owned by one query block during resolution.
 *
 * Clause environments share this working state. An aggregate written in a nested
 * query can belong to this block; the completed query fact publishes its occurrences.
 *
 * @visibility SqlSemantics
 */
final class AggregationScope
{
    /** @var array<int, Scalar> */
    private array $expressions = [];

    /**
     * @param list<VisibleRelation> $relations The relations introduced by this block
     */
    public function __construct(public readonly array $relations)
    {
    }

    /**
     * Records an aggregate occurrence once, preserving its identity and discovery order.
     */
    public function register(Scalar $expression): void
    {
        $this->expressions[spl_object_id($expression)] = $expression;
    }

    /**
     * Answers the aggregate occurrences assigned to this block so far.
     *
     * @return list<Scalar>
     */
    public function expressions(): array
    {
        return array_values($this->expressions);
    }

    /**
     * Tells whether a resolved relation occurrence belongs to this block.
     */
    public function contains(Relation $relation): bool
    {
        foreach ($this->relations as $visible) {
            if ($visible->relation === $relation) {
                return true;
            }
        }

        return false;
    }
}
