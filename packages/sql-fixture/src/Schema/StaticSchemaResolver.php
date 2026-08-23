<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * Resolves schemas from an in-memory, case-insensitive registry.
 */
final class StaticSchemaResolver implements SchemaResolverInterface
{
    /** @var array<string, TableSchema> Lower-cased table name => schema */
    private array $schemas = [];

    /**
     * @param iterable<TableSchema> $schemas
     */
    public function __construct(iterable $schemas = [])
    {
        foreach ($schemas as $schema) {
            $this->register($schema);
        }
    }

    public function register(TableSchema $schema): void
    {
        $this->schemas[$this->normalize($schema->tableName)] = $schema;
    }

    public function resolve(string $tableName): TableSchema
    {
        $schema = $this->schemas[$this->normalize($tableName)] ?? null;
        if ($schema === null) {
            throw SchemaNotFoundException::forTable($tableName, $this->tableNames());
        }

        return $schema;
    }

    public function has(string $tableName): bool
    {
        return isset($this->schemas[$this->normalize($tableName)]);
    }

    /**
     * @return list<string>
     */
    public function tableNames(): array
    {
        return array_keys($this->schemas);
    }

    private function normalize(string $tableName): string
    {
        $name = str_replace(['`', '"', '[', ']'], '', $tableName);

        $separator = strrpos($name, '.');
        if ($separator !== false) {
            $name = substr($name, $separator + 1);
        }

        return strtolower($name);
    }
}
