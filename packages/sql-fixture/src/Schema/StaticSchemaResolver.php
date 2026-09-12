<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * Resolves schemas from an in-memory, case-insensitive registry.
 */
final class StaticSchemaResolver implements SchemaResolverInterface
{
    /**
     * @var array<string, TableSchema> Lower-cased table name => schema
     */
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

    /**
     * Registers the table schema under its normalized name.
     */
    public function register(TableSchema $schema): void
    {
        $this->schemas[(new TableIdentifier())->normalize($schema->tableName)] = $schema;
    }

    /**
     * Returns the schema registered for the table, or reports that it is missing.
     * @throws SchemaNotFoundException
     */
    public function resolve(string $tableName): TableSchema
    {
        $schema = $this->schemas[(new TableIdentifier())->normalize($tableName)] ?? null;
        if ($schema === null) {
            throw SchemaNotFoundException::forTable($tableName, $this->tableNames());
        }

        return $schema;
    }

    /**
     * Reports whether the named table is registered.
     */
    public function has(string $tableName): bool
    {
        return isset($this->schemas[(new TableIdentifier())->normalize($tableName)]);
    }

    /**
     * @return list<string>
     */
    public function tableNames(): array
    {
        return array_keys($this->schemas);
    }

}
