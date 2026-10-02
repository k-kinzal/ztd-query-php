<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Diagnostic\InvalidConstruction;

/**
 * Closes the PHP paths that would change or re-create a semantic value without its constructor.
 *
 * @visibility public
 * @example Refusing to copy a semantic value
 *     clone new \SqlSemantics\Statement\Identifier\Name('id') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
trait Snapshot
{
    /**
     * Refuses a copy, which would blur which occurrence a value is.
     *
     * @throws InvalidConstruction Always
     */
    public function __clone()
    {
        throw new InvalidConstruction('Semantic values cannot be cloned.');
    }

    /**
     * Refuses a dynamic property.
     *
     * @throws InvalidConstruction Always
     */
    public function __set(string $name, mixed $value): void
    {
        throw new InvalidConstruction('Semantic values do not accept dynamic properties.');
    }

    /**
     * Refuses serialization, so that a value can only come from construction.
     *
     * @return array<string, mixed>
     * @throws InvalidConstruction Always
     */
    public function __serialize(): array
    {
        throw new InvalidConstruction('Semantic values must be constructed, not serialized.');
    }

    /**
     * Refuses restoration without construction.
     *
     * @param array<string, mixed> $data
     * @throws InvalidConstruction Always
     */
    public function __unserialize(array $data): void
    {
        throw new InvalidConstruction('Semantic values cannot be restored without construction.');
    }
}
