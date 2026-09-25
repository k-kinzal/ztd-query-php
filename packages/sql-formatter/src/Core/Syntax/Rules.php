<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Syntax;

/**
 * Grammar-owned layout roles supplied by a dialect implementation.
 *
 * @visibility SqlFormatter
 */
final class Rules
{
    /**
     * @param array<string, list<string>> $roles Grammar rules grouped by layout role
     * @param list<string> $headers Recognized clause keyword sequences
     */
    public function __construct(public readonly array $roles, public readonly array $headers)
    {
    }

    /**
     * Determines whether a grammar rule has a given layout role.
     */
    public function has(string $role, string $name): bool
    {
        return in_array($name, $this->roles[$role] ?? [], true);
    }
}
