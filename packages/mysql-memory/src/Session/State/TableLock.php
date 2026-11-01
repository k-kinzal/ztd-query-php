<?php

declare(strict_types=1);

namespace MySqlMemory\Session\State;

/**
 * One explicit table handle, including the namespace it resolved at acquisition.
 * A temporary handle cannot authorize access to a persistent namesake.
 *
 * @visibility MySqlMemory
 */
final class TableLock
{
    /**
     * Records the table, its access name and its requested lock mode.
     */
    public function __construct(public readonly string $schema, public readonly string $table, public readonly string $alias, public readonly bool $write, public readonly bool $temporary = false)
    {
    }

    /**
     * Retargets a persistent handle, retaining an explicit alias and its access mode.
     */
    public function renamed(string $schema, string $table): self
    {
        return new self($schema, $table, strcasecmp($this->alias, $this->table) === 0 ? $table : $this->alias, $this->write, $this->temporary);
    }
}
