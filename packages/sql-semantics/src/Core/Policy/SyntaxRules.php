<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

/**
 * Maps semantic roles onto the configured grammar's node and token names.
 *
 * @visibility SqlSemantics
 */
final class SyntaxRules
{
    /**
     * @param array<string, list<string>> $roles
     */
    public function __construct(private readonly array $roles)
    {
    }

    /**
     * @return list<string>
     */
    public function nodes(string $role): array
    {
        return $this->roles[$role] ?? [];
    }
}
