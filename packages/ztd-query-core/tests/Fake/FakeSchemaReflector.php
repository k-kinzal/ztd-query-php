<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\SchemaReflector;

/**
 * Fake SchemaReflector backed by an in-memory store.
 */
final class FakeSchemaReflector implements SchemaReflector
{
    /**
     * @var array<string, string>
     */
    private array $schemas;

    /**
     * @param array<string, string> $schemas Table name => CREATE TABLE SQL.
     */
    public function __construct(array $schemas = [])
    {
        $this->schemas = $schemas;
    }

    public function addTable(string $tableName, string $createSql): void
    {
        $this->schemas[$tableName] = $createSql;
    }

    public function getCreateStatement(string $tableName): ?string
    {
        return $this->schemas[$tableName] ?? null;
    }

    public function reflectAll(): array
    {
        return $this->schemas;
    }
}
