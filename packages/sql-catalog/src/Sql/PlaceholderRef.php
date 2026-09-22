<?php

declare(strict_types=1);

namespace SqlCatalog\Sql;

/**
 * One bind parameter found in a statement.
 *
 * @visibility root
 */
final class PlaceholderRef
{
    /**
     * @param string $token The parameter exactly as written, such as `?` or `:id`
     * @param int $position The zero-based order of the parameter within the statement
     * @param string|null $name The parameter name, for `:name` and `$1` style parameters
     */
    public function __construct(
        public readonly string $token,
        public readonly int $position,
        public readonly ?string $name = null,
    ) {
    }

    /**
     * Whether the parameter is bound by position rather than by name.
     */
    public function isPositional(): bool
    {
        return $this->name === null;
    }

    /**
     * The key a report uses for the parameter.
     */
    public function key(): string
    {
        return $this->name ?? (string) $this->position;
    }
}
