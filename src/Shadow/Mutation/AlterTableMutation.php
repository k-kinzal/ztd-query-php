<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowStore;
use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use ZtdQuery\Platform\MySql\MySqlDialect;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Token;

/**
 * Applies ALTER TABLE operation to the virtual schema.
 * This mutation modifies the table definition in the SchemaRegistry.
 */
final class AlterTableMutation implements ShadowMutation
{
    /**
     * Table name to alter.
     *
     * @var string
     */
    private string $tableName;

    /**
     * ALTER statement.
     *
     * @var AlterStatement
     */
    private AlterStatement $alterStatement;

    /**
     * Schema registry to modify.
     *
     * @var SchemaRegistry
     */
    private SchemaRegistry $schemaRegistry;

    /**
     * @param string $tableName The name of the table to alter.
     * @param AlterStatement $alterStatement The ALTER statement to apply.
     * @param SchemaRegistry $schemaRegistry The schema registry to modify.
     */
    public function __construct(
        string $tableName,
        AlterStatement $alterStatement,
        SchemaRegistry $schemaRegistry
    ) {
        $this->tableName = $tableName;
        $this->alterStatement = $alterStatement;
        $this->schemaRegistry = $schemaRegistry;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $existingSql = $this->schemaRegistry->get($this->tableName);
        if ($existingSql === null) {
            throw new SchemaNotFoundException($this->alterStatement->build(), $this->tableName);
        }

        $parser = MySqlDialect::createParser($existingSql);
        if ($parser->statements === []) {
            throw new \RuntimeException("Failed to parse existing schema for '{$this->tableName}'.");
        }

        $createStmt = $parser->statements[0];
        if (!$createStmt instanceof CreateStatement) {
            throw new \RuntimeException("Existing schema for '{$this->tableName}' is not a CREATE TABLE statement.");
        }

        // Apply each ALTER operation
        foreach ($this->alterStatement->altered ?? [] as $op) {
            $this->applyOperation($createStmt, $op, $store);
        }

        // Rebuild and re-register the CREATE TABLE SQL
        $newSql = $createStmt->build();
        $this->schemaRegistry->unregister($this->tableName);
        $this->schemaRegistry->register($this->tableName, $newSql);
    }

    /**
     * Apply a single ALTER operation to the CREATE statement.
     */
    private function applyOperation(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store): void
    {
        $options = $op->options;
        if ($options->isEmpty()) {
            return;
        }

        if ($options->has('ADD') && $options->has('COLUMN')) {
            $this->applyAddColumn($createStmt, $op);
        } elseif ($options->has('ADD') && !$options->has('COLUMN') && !$options->has('PRIMARY KEY') && !$options->has('FOREIGN') && !$options->has('INDEX') && !$options->has('UNIQUE') && !$options->has('KEY') && !$options->has('FULLTEXT') && !$options->has('SPATIAL') && !$options->has('CONSTRAINT') && !$options->has('PARTITION') && !$this->hasUnsupportedKeywordInUnknown($op)) {
            // ADD without COLUMN keyword - still add column
            $this->applyAddColumn($createStmt, $op);
        } elseif ($options->has('DROP') && $options->has('COLUMN')) {
            $this->applyDropColumn($createStmt, $op, $store);
        } elseif ($options->has('DROP') && !$options->has('COLUMN') && !$options->has('PRIMARY KEY') && !$options->has('FOREIGN') && !$options->has('INDEX') && !$options->has('KEY') && !$options->has('CONSTRAINT')) {
            // DROP without COLUMN keyword - still drop column
            $this->applyDropColumn($createStmt, $op, $store);
        } elseif ($options->has('MODIFY') || $options->has('MODIFY COLUMN')) {
            $this->applyModifyColumn($createStmt, $op);
        } elseif ($options->has('CHANGE') || $options->has('CHANGE COLUMN')) {
            $this->applyChangeColumn($createStmt, $op, $store);
        } elseif ($options->has('RENAME') && $options->has('TO') && !$options->has('INDEX') && !$options->has('KEY') && !$options->has('COLUMN')) {
            $this->applyRenameTable($op, $store);
        } elseif ($options->has('ADD') && $options->has('PRIMARY KEY')) {
            $this->applyAddPrimaryKey($createStmt, $op);
        } elseif ($options->has('DROP') && $options->has('PRIMARY KEY')) {
            $this->applyDropPrimaryKey($createStmt);
        } elseif ($options->has('ADD') && $options->has('FOREIGN')) {
            // Foreign key constraints are just metadata in ZTD, so we can skip
        } elseif ($options->has('DROP') && $options->has('FOREIGN')) {
            // Foreign key constraints are just metadata in ZTD, so we can skip
        } elseif ($options->has('RENAME') && $options->has('COLUMN')) {
            $this->applyRenameColumn($createStmt, $op, $store);
        } elseif ($options->has('ALTER') && ($options->has('SET DEFAULT') || $options->has('DROP DEFAULT'))) {
            // SET DEFAULT / DROP DEFAULT on column - not fully supported, ignore for now
        } else {
            throw new UnsupportedSqlException(AlterOperation::build($op), 'ALTER TABLE');
        }
    }

    /**
     * Add a column to the CREATE statement.
     */
    private function applyAddColumn(CreateStatement $createStmt, AlterOperation $op): void
    {
        if (!is_array($createStmt->fields)) {
            $createStmt->fields = [];
        }

        // Build the column definition from operation tokens
        $columnDef = $this->buildColumnDefinition($op);
        if ($columnDef !== null) {
            $columnName = $this->normalizeColumnName($columnDef->name ?? '');

            // Check if column already exists
            if ($columnName !== '' && $this->schemaRegistry->hasColumn($this->tableName, $columnName)) {
                throw new ColumnAlreadyExistsException(
                    $this->alterStatement->build(),
                    $this->tableName,
                    $columnName
                );
            }

            $createStmt->fields[] = $columnDef;
        }
    }

    /**
     * Drop a column from the CREATE statement.
     */
    private function applyDropColumn(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store): void
    {
        $columnName = $this->getColumnName($op);
        if ($columnName === null) {
            return;
        }

        // Check if column exists
        if (!$this->schemaRegistry->hasColumn($this->tableName, $columnName)) {
            throw new ColumnNotFoundException(
                $this->alterStatement->build(),
                $this->tableName,
                $columnName
            );
        }

        if (!is_array($createStmt->fields)) {
            return;
        }

        $createStmt->fields = array_values(array_filter(
            $createStmt->fields,
            fn ($field) => $this->normalizeColumnName($field->name ?? '') !== $columnName
        ));

        // Also remove the column from shadow store data
        $this->removeColumnFromStore($store, $columnName);
    }

    /**
     * Modify a column in the CREATE statement.
     */
    private function applyModifyColumn(CreateStatement $createStmt, AlterOperation $op): void
    {
        $columnDef = $this->buildColumnDefinition($op);
        if ($columnDef === null) {
            return;
        }

        $columnName = $this->normalizeColumnName($columnDef->name ?? '');
        if ($columnName === '' || !is_array($createStmt->fields)) {
            return;
        }

        // Check if column exists
        if (!$this->schemaRegistry->hasColumn($this->tableName, $columnName)) {
            throw new ColumnNotFoundException(
                $this->alterStatement->build(),
                $this->tableName,
                $columnName
            );
        }

        foreach ($createStmt->fields as $i => $field) {
            if ($this->normalizeColumnName($field->name ?? '') === $columnName) {
                $createStmt->fields[$i] = $columnDef;
                break;
            }
        }
    }

    /**
     * Change a column name/definition in the CREATE statement.
     */
    private function applyChangeColumn(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store): void
    {
        $oldColumnName = $this->getColumnName($op);
        if ($oldColumnName === null) {
            return;
        }

        // Check if old column exists
        if (!$this->schemaRegistry->hasColumn($this->tableName, $oldColumnName)) {
            throw new ColumnNotFoundException(
                $this->alterStatement->build(),
                $this->tableName,
                $oldColumnName
            );
        }

        // For CHANGE, the new column definition follows the old column name
        $newColumnDef = $this->buildColumnDefinitionFromUnknown($op);
        if ($newColumnDef === null) {
            return;
        }

        $newColumnName = $this->normalizeColumnName($newColumnDef->name ?? '');

        if (!is_array($createStmt->fields)) {
            return;
        }

        foreach ($createStmt->fields as $i => $field) {
            if ($this->normalizeColumnName($field->name ?? '') === $oldColumnName) {
                $createStmt->fields[$i] = $newColumnDef;
                break;
            }
        }

        // Rename column in shadow store data if name changed
        if ($oldColumnName !== $newColumnName) {
            $this->renameColumnInStore($store, $oldColumnName, $newColumnName);
        }
    }

    /**
     * Rename a column using ALTER TABLE ... RENAME COLUMN ... TO ... syntax.
     */
    private function applyRenameColumn(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store): void
    {
        $oldColumnName = $this->getColumnName($op);
        if ($oldColumnName === null) {
            return;
        }

        // Check if old column exists
        if (!$this->schemaRegistry->hasColumn($this->tableName, $oldColumnName)) {
            throw new ColumnNotFoundException(
                $this->alterStatement->build(),
                $this->tableName,
                $oldColumnName
            );
        }

        // Get new column name from the TO option
        $toValue = $op->options !== null ? $op->options->has('TO') : false;
        if (!is_string($toValue) || $toValue === '') {
            return;
        }
        $newColumnName = $this->normalizeColumnName($toValue);

        if (!is_array($createStmt->fields)) {
            return;
        }

        // Find and rename the column in schema
        foreach ($createStmt->fields as $field) {
            if ($this->normalizeColumnName($field->name ?? '') === $oldColumnName) {
                $field->name = $newColumnName;
                break;
            }
        }

        // Rename column in shadow store data
        if ($oldColumnName !== $newColumnName) {
            $this->renameColumnInStore($store, $oldColumnName, $newColumnName);
        }
    }

    /**
     * Rename the table.
     */
    private function applyRenameTable(AlterOperation $op, ShadowStore $store): void
    {
        // Get the new table name from the TO option
        $toValue = $op->options !== null ? $op->options->has('TO') : false;
        if (!is_string($toValue) || $toValue === '') {
            return;
        }

        $newName = $this->normalizeColumnName($toValue);

        // Move data from old table to new table
        $data = $store->get($this->tableName);
        $store->set($newName, $data);
        $store->set($this->tableName, []);

        // Get existing schema, rename, and re-register
        $existingSql = $this->schemaRegistry->get($this->tableName);
        if ($existingSql !== null) {
            // Replace table name in CREATE TABLE statement
            $newSql = preg_replace(
                '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?' . preg_quote($this->tableName, '/') . '[`"]?/i',
                'CREATE TABLE `' . $newName . '`',
                $existingSql
            );
            $this->schemaRegistry->unregister($this->tableName);
            $this->schemaRegistry->register($newName, $newSql ?? $existingSql);
        }

        // Update the table name for subsequent operations
        $this->tableName = $newName;
    }

    /**
     * Add a primary key constraint.
     */
    private function applyAddPrimaryKey(CreateStatement $createStmt, AlterOperation $op): void
    {
        // Primary key handling is done in SchemaRegistry via getPrimaryKeys()
        // We need to add a PRIMARY KEY constraint to the fields
        $keyDef = new CreateDefinition();
        $keyDef->key = new \PhpMyAdmin\SqlParser\Components\Key();
        $keyDef->key->type = 'PRIMARY KEY';
        $keyDef->key->columns = [];

        // Extract column names from unknown tokens
        $unknownTokens = is_array($op->unknown) ? $op->unknown : [];
        foreach ($unknownTokens as $token) {
            if ($token->type === Token::TYPE_SYMBOL) {
                $tokenValue = is_string($token->value) ? $token->value : '';
                $colName = str_replace('`', '', $tokenValue);
                $keyDef->key->columns[] = ['name' => $colName];
            }
        }

        if (!is_array($createStmt->fields)) {
            $createStmt->fields = [];
        }
        $createStmt->fields[] = $keyDef;
    }

    /**
     * Drop the primary key constraint.
     */
    private function applyDropPrimaryKey(CreateStatement $createStmt): void
    {
        if (!is_array($createStmt->fields)) {
            return;
        }

        // Remove PRIMARY KEY from column options
        foreach ($createStmt->fields as $field) {
            if ($field->options !== null && $field->options->has('PRIMARY KEY')) {
                $field->options->remove('PRIMARY KEY');
            }
        }

        // Remove standalone PRIMARY KEY constraint
        $createStmt->fields = array_values(array_filter(
            $createStmt->fields,
            fn ($field) => $field->key === null || $field->key->type !== 'PRIMARY KEY'
        ));
    }

    /**
     * Build a CreateDefinition from an AlterOperation.
     */
    private function buildColumnDefinition(AlterOperation $op): ?CreateDefinition
    {
        $field = $op->field;
        if ($field === null) {
            return null;
        }

        $columnName = is_string($field) ? $field : ($field->column ?? $field->expr ?? null);
        if (!is_string($columnName)) {
            return null;
        }

        $columnName = $this->normalizeColumnName($columnName);

        // Build column definition from unknown tokens
        $tokens = is_array($op->unknown) ? $op->unknown : [];
        $typeStr = '';
        foreach ($tokens as $token) {
            $typeStr .= (string) $token->token;
        }

        $defSql = "CREATE TABLE t (`$columnName` $typeStr)";
        $parser = MySqlDialect::createParser($defSql);
        if ($parser->statements === [] || !$parser->statements[0] instanceof CreateStatement) {
            return null;
        }

        $tempCreate = $parser->statements[0];
        if (!is_array($tempCreate->fields) || $tempCreate->fields === []) {
            return null;
        }

        return $tempCreate->fields[0];
    }

    /**
     * Build a CreateDefinition from unknown tokens (for CHANGE COLUMN).
     */
    private function buildColumnDefinitionFromUnknown(AlterOperation $op): ?CreateDefinition
    {
        $tokens = is_array($op->unknown) ? $op->unknown : [];
        if ($tokens === []) {
            return null;
        }

        $tokenStr = '';
        foreach ($tokens as $token) {
            $tokenStr .= (string) $token->token;
        }

        $defSql = "CREATE TABLE t ($tokenStr)";
        $parser = MySqlDialect::createParser($defSql);
        if ($parser->statements === [] || !$parser->statements[0] instanceof CreateStatement) {
            return null;
        }

        $tempCreate = $parser->statements[0];
        if (!is_array($tempCreate->fields) || $tempCreate->fields === []) {
            return null;
        }

        return $tempCreate->fields[0];
    }

    /**
     * Get the column name from an AlterOperation.
     */
    private function getColumnName(AlterOperation $op): ?string
    {
        $field = $op->field;
        if ($field === null) {
            return null;
        }

        $name = is_string($field) ? $field : ($field->column ?? $field->expr ?? null);
        if (!is_string($name)) {
            return null;
        }

        return $this->normalizeColumnName($name);
    }

    /**
     * Normalize a column name by removing backticks.
     */
    private function normalizeColumnName(string $name): string
    {
        return str_replace('`', '', $name);
    }

    /**
     * Remove a column from all rows in shadow store.
     */
    private function removeColumnFromStore(ShadowStore $store, string $columnName): void
    {
        $rows = $store->get($this->tableName);
        if ($rows === []) {
            return;
        }

        $newRows = [];
        foreach ($rows as $row) {
            unset($row[$columnName]);
            $newRows[] = $row;
        }
        $store->set($this->tableName, $newRows);
    }

    /**
     * Rename a column in all rows in shadow store.
     */
    private function renameColumnInStore(ShadowStore $store, string $oldName, string $newName): void
    {
        $rows = $store->get($this->tableName);
        if ($rows === []) {
            return;
        }

        $newRows = [];
        foreach ($rows as $row) {
            if (array_key_exists($oldName, $row)) {
                $row[$newName] = $row[$oldName];
                unset($row[$oldName]);
            }
            $newRows[] = $row;
        }
        $store->set($this->tableName, $newRows);
    }

    /**
     * Check if unknown tokens contain unsupported keywords.
     */
    private function hasUnsupportedKeywordInUnknown(AlterOperation $op): bool
    {
        $unsupportedPatterns = [
            'SPATIAL INDEX',
            'SPATIAL KEY',
            'PARTITION',
        ];

        $unknownTokens = is_array($op->unknown) ? $op->unknown : [];
        foreach ($unknownTokens as $token) {
            $tokenValue = is_string($token->value) ? $token->value : '';
            $value = strtoupper($tokenValue);
            foreach ($unsupportedPatterns as $pattern) {
                if (str_contains($value, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
