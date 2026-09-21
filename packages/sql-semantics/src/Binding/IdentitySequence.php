<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

/**
 * Allocates deterministic occurrence identities for one bound statement.
 *
 * @visibility SqlSemantics
 */
final class IdentitySequence
{
    /**
     * Next unused relation ordinal.
     */
    public int $relation = 0;

    /**
     * Next unused join ordinal.
     */
    public int $join = 0;

    /**
     * Next unused query scope ordinal.
     */
    public int $scope = 0;

    /**
     * Allocates a query scope.
     */
    public function scope(): string
    {
        return 's' . $this->scope++;
    }

    /**
     * Allocates the next relation occurrence ID.
     */
    public function relation(): string
    {
        return 'r' . $this->relation++;
    }

    /**
     * Allocates the next join ID.
     */
    public function join(): string
    {
        return 'j' . $this->join++;
    }
}
