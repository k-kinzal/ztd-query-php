<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Platform\MySql\MySqlDialect;

/**
 * Caches schema metadata used during rewriting.
 */
class SchemaRegistry
{
    /**
     * Cached CREATE TABLE statements by table name.
     *
     * @var array<string, string>
     */
    private array $schemas = [];

    /**
     * Cached primary key columns by table name.
     *
     * @var array<string, array<int, string>>
     */
    private array $primaryKeys = [];

    /**
     * Cached column types by table name.
     *
     * @var array<string, array<string, string>>
     */
    private array $columnTypes = [];

    /**
     * Cached NOT NULL columns by table name.
     *
     * @var array<string, array<int, string>>
     */
    private array $notNullColumns = [];

    /**
     * Cached UNIQUE constraints by table name.
     * Maps key name to column list.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private array $uniqueConstraints = [];

    /**
     * Optional reflector for schema introspection.
     *
     * @var SchemaReflector|null
     */
    private ?SchemaReflector $reflector;

    /**
     * @param SchemaReflector|null $reflector Optional schema reflector.
     */
    public function __construct(?SchemaReflector $reflector = null)
    {
        $this->reflector = $reflector;
    }

    /**
     * Register a CREATE TABLE statement for a table.
     */
    public function register(string $tableName, string $createTableSql): void
    {
        $this->schemas[$tableName] = $createTableSql;
    }

    /**
     * Get CREATE TABLE SQL for a table, using schema cache or reflector.
     */
    public function get(string $tableName): ?string
    {
        $this->ensureSchema($tableName);
        return $this->schemas[$tableName] ?? null;
    }

    /**
     * Check if a table has a virtual (manually registered) schema definition.
     */
    public function hasVirtualTableDefinition(string $tableName): bool
    {
        return isset($this->schemas[$tableName]);
    }

    /**
     * Get all cached CREATE TABLE statements.
     *
     * @return array<string, string>
     */
    public function getAll(): array
    {
        return $this->schemas;
    }

    /**
     * Check if the registry has any tables registered.
     *
     * @return bool True if at least one table is registered.
     */
    public function hasAnyTables(): bool
    {
        return $this->schemas !== [];
    }

    /**
     * Clear cached schemas, primary keys, column types, and constraints.
     */
    public function clear(): void
    {
        $this->schemas = [];
        $this->primaryKeys = [];
        $this->columnTypes = [];
        $this->notNullColumns = [];
        $this->uniqueConstraints = [];
    }

    /**
     * Unregister a table from the schema registry.
     */
    public function unregister(string $tableName): void
    {
        unset($this->schemas[$tableName]);
        unset($this->primaryKeys[$tableName]);
        unset($this->columnTypes[$tableName]);
        unset($this->notNullColumns[$tableName]);
        unset($this->uniqueConstraints[$tableName]);
    }

    /**
     * Get column names for a table, using schema cache or reflector.
     *
     * @return array<int, string>|null
     */
    public function getColumns(string $tableName): ?array
    {
        // get() already calls ensureSchema, so don't call it twice
        $sql = $this->get($tableName);
        if ($sql === null) {
            return null;
        }

        $parser = MySqlDialect::createParser($sql);
        if ($parser->statements === []) {
            return null;
        }

        $stmt = $parser->statements[0];
        if (!$stmt instanceof CreateStatement) {
            return null;
        }

        if (!is_iterable($stmt->fields)) {
            return null;
        }

        $columns = [];
        foreach ($stmt->fields as $field) {
            $name = $field->name ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }
            $columns[] = str_replace('`', '', $name);
        }

        return $columns;
    }

    /**
     * Get column types for a table, mapping column name to MySQL type string.
     *
     * Returns types like: INT, BIGINT, DECIMAL(10,2), VARCHAR(255), DATETIME, etc.
     *
     * @return array<string, string>|null
     */
    public function getColumnTypes(string $tableName): ?array
    {
        if (isset($this->columnTypes[$tableName])) {
            return $this->columnTypes[$tableName];
        }

        $this->ensureSchema($tableName);
        $sql = $this->get($tableName);
        if ($sql === null) {
            return null;
        }

        $parser = MySqlDialect::createParser($sql);
        if ($parser->statements === []) {
            return null;
        }

        $stmt = $parser->statements[0];
        if (!$stmt instanceof CreateStatement) {
            return null;
        }

        if (!is_iterable($stmt->fields)) {
            return null;
        }

        $types = [];
        foreach ($stmt->fields as $field) {
            $name = $field->name ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }
            $columnName = str_replace('`', '', $name);

            if ($field->type !== null && $field->type->name !== null) {
                $typeName = strtoupper($field->type->name);
                if (!empty($field->type->parameters)) {
                    $typeName .= '(' . implode(',', $field->type->parameters) . ')';
                }
                $types[$columnName] = $typeName;
            }
        }

        $this->columnTypes[$tableName] = $types;

        return $types;
    }

    /**
     * Get primary keys for a table, using schema cache or reflector.
     *
     * @return array<int, string>
     */
    public function getPrimaryKeys(string $tableName): array
    {
        if (isset($this->primaryKeys[$tableName])) {
            return $this->primaryKeys[$tableName];
        }

        // Try to extract from registered CREATE TABLE SQL
        $this->ensureSchema($tableName);
        $sql = $this->get($tableName);
        if ($sql !== null) {
            $keys = $this->extractPrimaryKeysFromSql($sql);
            if ($keys !== []) {
                $this->primaryKeys[$tableName] = $keys;
                return $keys;
            }
        }

        // Fall back to reflector
        if ($this->reflector !== null) {
            try {
                $keys = $this->reflector->getPrimaryKeys($tableName);
                $this->primaryKeys[$tableName] = $keys;
                return $keys;
            } catch (\PDOException $e) {
                // Table doesn't exist in the database - return empty array
                return [];
            }
        }

        return [];
    }

    /**
     * Extract primary keys from CREATE TABLE SQL.
     *
     * @return array<int, string>
     */
    private function extractPrimaryKeysFromSql(string $sql): array
    {
        $parser = MySqlDialect::createParser($sql);
        if ($parser->statements === []) {
            return [];
        }

        $stmt = $parser->statements[0];
        if (!$stmt instanceof CreateStatement) {
            return [];
        }

        if (!is_iterable($stmt->fields)) {
            return [];
        }

        $primaryKeys = [];
        foreach ($stmt->fields as $field) {
            // Check for inline PRIMARY KEY in column options (e.g., "id INT PRIMARY KEY")
            if ($field->options !== null && $field->options->has('PRIMARY KEY')) {
                $name = $field->name ?? null;
                if (is_string($name) && $name !== '') {
                    $primaryKeys[] = str_replace('`', '', $name);
                }
            }

            // Check for standalone PRIMARY KEY constraint (e.g., "PRIMARY KEY (id)")
            if ($field->key !== null && $field->key->type === 'PRIMARY KEY') {
                foreach ($field->key->columns as $col) {
                    $colName = $col['name'] ?? null;
                    if (is_string($colName) && $colName !== '') {
                        $primaryKeys[] = str_replace('`', '', $colName);
                    }
                }
            }
        }

        return $primaryKeys;
    }

    /**
     * Get columns with NOT NULL constraint for a table.
     *
     * @return array<int, string>
     */
    public function getNotNullColumns(string $tableName): array
    {
        if (isset($this->notNullColumns[$tableName])) {
            return $this->notNullColumns[$tableName];
        }

        $this->ensureSchema($tableName);
        $sql = $this->get($tableName);
        if ($sql === null) {
            return [];
        }

        $columns = $this->extractNotNullColumnsFromSql($sql);
        $this->notNullColumns[$tableName] = $columns;

        return $columns;
    }

    /**
     * Extract NOT NULL columns from CREATE TABLE SQL.
     *
     * @return array<int, string>
     */
    private function extractNotNullColumnsFromSql(string $sql): array
    {
        $parser = MySqlDialect::createParser($sql);
        if ($parser->statements === []) {
            return [];
        }

        $stmt = $parser->statements[0];
        if (!$stmt instanceof CreateStatement) {
            return [];
        }

        if (!is_iterable($stmt->fields)) {
            return [];
        }

        $notNullColumns = [];
        foreach ($stmt->fields as $field) {
            $name = $field->name ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }

            // Check for NOT NULL in column options
            if ($field->options !== null && $field->options->has('NOT NULL')) {
                $notNullColumns[] = str_replace('`', '', $name);
            }

            // PRIMARY KEY columns are implicitly NOT NULL
            if ($field->options !== null && $field->options->has('PRIMARY KEY')) {
                $columnName = str_replace('`', '', $name);
                if (!in_array($columnName, $notNullColumns, true)) {
                    $notNullColumns[] = $columnName;
                }
            }
        }

        return $notNullColumns;
    }

    /**
     * Get UNIQUE constraints for a table.
     * Returns a map of constraint name to column list.
     *
     * @return array<string, array<int, string>>
     */
    public function getUniqueConstraints(string $tableName): array
    {
        if (isset($this->uniqueConstraints[$tableName])) {
            return $this->uniqueConstraints[$tableName];
        }

        $this->ensureSchema($tableName);
        $sql = $this->get($tableName);
        if ($sql === null) {
            return [];
        }

        $constraints = $this->extractUniqueConstraintsFromSql($sql);
        $this->uniqueConstraints[$tableName] = $constraints;

        return $constraints;
    }

    /**
     * Extract UNIQUE constraints from CREATE TABLE SQL.
     *
     * @return array<string, array<int, string>>
     */
    private function extractUniqueConstraintsFromSql(string $sql): array
    {
        $parser = MySqlDialect::createParser($sql);
        if ($parser->statements === []) {
            return [];
        }

        $stmt = $parser->statements[0];
        if (!$stmt instanceof CreateStatement) {
            return [];
        }

        if (!is_iterable($stmt->fields)) {
            return [];
        }

        $constraints = [];
        $uniqueIndex = 0;

        foreach ($stmt->fields as $field) {
            $name = $field->name ?? null;

            // Check for inline UNIQUE in column options (e.g., "email VARCHAR(255) UNIQUE")
            if (is_string($name) && $name !== '' && $field->options !== null && $field->options->has('UNIQUE')) {
                $columnName = str_replace('`', '', $name);
                $keyName = $columnName . '_UNIQUE';
                $constraints[$keyName] = [$columnName];
            }

            // Check for standalone UNIQUE constraint (e.g., "UNIQUE KEY (email)")
            if ($field->key !== null && ($field->key->type === 'UNIQUE' || $field->key->type === 'UNIQUE KEY')) {
                $columns = [];
                foreach ($field->key->columns as $col) {
                    $colName = $col['name'] ?? null;
                    if (is_string($colName) && $colName !== '') {
                        $columns[] = str_replace('`', '', $colName);
                    }
                }
                if ($columns !== []) {
                    $keyName = $field->key->name !== '' ? $field->key->name : ('unique_' . $uniqueIndex++);
                    $constraints[$keyName] = $columns;
                }
            }
        }

        return $constraints;
    }

    /**
     * Check if a column exists in a table.
     */
    public function hasColumn(string $tableName, string $columnName): bool
    {
        $columns = $this->getColumns($tableName);
        if ($columns === null) {
            return false;
        }

        $normalizedColumnName = str_replace('`', '', $columnName);

        return in_array($normalizedColumnName, $columns, true);
    }

    private function ensureSchema(string $tableName): void
    {
        if ($this->reflector === null) {
            return;
        }

        if (isset($this->schemas[$tableName])) {
            return;
        }

        try {
            $createSql = $this->reflector->getCreateStatement($tableName);
            if ($createSql !== null) {
                $this->schemas[$tableName] = $createSql;
            }
        } catch (\PDOException $e) {
            // Table doesn't exist in the database - this is expected for virtual tables
        }
    }
}
