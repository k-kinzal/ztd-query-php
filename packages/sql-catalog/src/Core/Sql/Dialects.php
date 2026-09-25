<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Sql;

/**
 * Application-supplied SQL policies and connection identities.
 *
 * @visibility root
 */
final class Dialects
{
    /**
     * @param array<string, Dialect> $dialects Policies keyed by configured identity
     * @param array<string, string> $connections Framework class names mapped to policy identities
     */
    public function __construct(private readonly array $dialects = [], private readonly array $connections = [])
    {
    }

    /**
     * The configured policy, or null when the language is unresolved.
     */
    public function find(?string $name): ?Dialect
    {
        return $name === null ? null : ($this->dialects[$name] ?? null);
    }

    /**
     * Whether the application registered this connection class.
     */
    public function hasConnection(?string $class): bool
    {
        return $class !== null && isset($this->connections[$class]);
    }

    /**
     * The language implied by a connection, otherwise the configured default.
     */
    public function connection(?string $class, ?string $default): ?string
    {
        return $class === null ? $default : ($this->connections[$class] ?? $default);
    }
}
