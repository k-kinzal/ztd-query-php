<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation;

use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

/**
 * Closes ordinary mutation, cloning, and restoration paths on semantic values.
 *
 * New values must pass their constructors. This is not a sandbox against hostile
 * Reflection or closures bound to private class scope.
 * @visibility SqlSemantics
 */
trait Snapshot
{
    /**
     * Entity identity is established once by construction.
     * @throws InvalidConstruction
     */
    public function __clone(): void
    {
        throw new InvalidConstruction('Semantic values cannot be cloned.');
    }

    /**
     * Dynamic state is never part of a completed semantic value.
     * @throws InvalidConstruction
     */
    public function __set(string $name, mixed $value): void
    {
        throw new InvalidConstruction('Semantic values do not accept dynamic properties.');
    }

    /**
     * There is no serialized snapshot format or restoration API.
     * @return array<array-key, mixed>
     * @throws InvalidConstruction
     */
    public function __serialize(): array
    {
        throw new InvalidConstruction('Semantic values must be constructed, not serialized.');
    }

    /**
     * Rejects even hand-written serialized payloads before any properties are set.
     * @param array<array-key, mixed> $data
     * @throws InvalidConstruction
     */
    public function __unserialize(array $data): void
    {
        throw new InvalidConstruction('Semantic values cannot be restored without construction.');
    }
}
