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

    /**
     * Remembers a table so a plan can name it.
     *
     * @param TableSchema $schema Table to remember
     */
    public function register(TableSchema $schema): void
    {
        $this->schemas[$this->normalize($schema->tableName)] = $schema;
    }

    /**
     * Answers a table that was registered.
     *
     * @param string $tableName Table to answer for
     *
     * @return TableSchema The table
     *
     * @throws SchemaNotFoundException When no such table was registered
     */
    public function resolve(string $tableName): TableSchema
    {
        $schema = $this->schemas[$this->normalize($tableName)] ?? null;
        if ($schema === null) {
            throw SchemaNotFoundException::forTable($tableName, $this->tableNames());
        }

        return $schema;
    }

    /**
     * Reports whether a table was registered.
     *
     * @param string $tableName Table to answer for
     *
     * @return bool True when it can be resolved
     */
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

    /**
     * Answers the key a table is filed under.
     *
     * A caller may write a table quoted, schema-qualified, or in any case, and all
     * of those name the same table, so the quotes and the schema come off and what
     * is left is lowercased.
     *
     * @param string $tableName Name as the caller wrote it
     *
     * @return string The key it is filed under
     */
    public function normalize(string $tableName): string
    {
        $name = str_replace(['`', '"', '[', ']'], '', $tableName);

        $separator = strrpos($name, '.');
        if ($separator !== false) {
            $name = substr($name, $separator + 1);
        }

        return strtolower($name);
    }
}
