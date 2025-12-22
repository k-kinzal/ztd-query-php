<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation;

use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;
use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Token;

/**
 * Applies ALTER TABLE operation to the virtual schema.
 * This mutation modifies the table definition in the TableDefinitionRegistry.
 */
final class AlterTableMutation implements ShadowMutation
{
    private string $tableName;
    private AlterStatement $alterStatement;
    private TableDefinitionRegistry $registry;
    private SchemaParser $schemaParser;

    public function __construct(
        string $tableName,
        AlterStatement $alterStatement,
        TableDefinitionRegistry $registry,
        SchemaParser $schemaParser
    ) {
        $this->tableName = $tableName;
        $this->alterStatement = $alterStatement;
        $this->registry = $registry;
        $this->schemaParser = $schemaParser;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $definition = $this->registry->get($this->tableName);
        if ($definition === null) {
            throw new SchemaNotFoundException($this->alterStatement->build(), $this->tableName);
        }

        $createSql = $this->buildCreateTableSql($definition);

        $parser = new \PhpMyAdmin\SqlParser\Parser($createSql);
        if ($parser->statements === []) {
            throw new \RuntimeException("Failed to parse reconstructed schema for '{$this->tableName}'.");
        }

        $createStmt = $parser->statements[0];
        if (!$createStmt instanceof CreateStatement) {
            throw new \RuntimeException("Reconstructed schema for '{$this->tableName}' is not a CREATE TABLE statement.");
        }

        foreach ($this->alterStatement->altered ?? [] as $op) {
            $this->applyOperation($createStmt, $op, $store, $definition);
        }

        $newSql = $createStmt->build();
        $newDefinition = $this->schemaParser->parse($newSql);
        if ($newDefinition === null) {
            throw new \RuntimeException("Failed to parse altered schema for '{$this->tableName}'.");
        }

        $this->registry->unregister($this->tableName);
        $this->registry->register($this->tableName, $newDefinition);
    }

    /**
     * Build a CREATE TABLE SQL from a TableDefinition.
     */
    private function buildCreateTableSql(TableDefinition $definition): string
    {
        $columnDefs = [];
        foreach ($definition->columns as $column) {
            $type = isset($definition->typedColumns[$column])
                ? $definition->typedColumns[$column]->nativeType
                : ($definition->columnTypes[$column] ?? 'TEXT');
            $def = "`{$column}` {$type}";

            if (in_array($column, $definition->notNullColumns, true)) {
                $def .= ' NOT NULL';
            }

            if (in_array($column, $definition->primaryKeys, true) && count($definition->primaryKeys) === 1) {
                $def .= ' PRIMARY KEY';
            }

            $columnDefs[] = $def;
        }

        if (count($definition->primaryKeys) > 1) {
            $pkCols = array_map(fn (string $c) => "`{$c}`", $definition->primaryKeys);
            $columnDefs[] = 'PRIMARY KEY (' . implode(', ', $pkCols) . ')';
        }

        foreach ($definition->uniqueConstraints as $keyName => $columns) {
            $ukCols = array_map(fn (string $c) => "`{$c}`", $columns);
            $columnDefs[] = "UNIQUE KEY `{$keyName}` (" . implode(', ', $ukCols) . ')';
        }

        return "CREATE TABLE `{$this->tableName}` (" . implode(', ', $columnDefs) . ')';
    }

    /**
     * Apply a single ALTER operation to the CREATE statement.
     */
    private function applyOperation(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store, TableDefinition $definition): void
    {
        $options = $op->options;
        if ($options->isEmpty()) {
            return;
        }

        if (self::optionSet($options, 'ADD') && self::optionSet($options, 'COLUMN')) {
            $this->applyAddColumn($createStmt, $op, $definition);
        } elseif (self::optionSet($options, 'ADD') && !self::optionSet($options, 'COLUMN') && !self::optionSet($options, 'PRIMARY KEY') && !self::optionSet($options, 'FOREIGN') && !self::optionSet($options, 'INDEX') && !self::optionSet($options, 'UNIQUE') && !self::optionSet($options, 'KEY') && !self::optionSet($options, 'FULLTEXT') && !self::optionSet($options, 'SPATIAL') && !self::optionSet($options, 'CONSTRAINT') && !self::optionSet($options, 'PARTITION') && !$this->hasUnsupportedKeywordInUnknown($op)) {
            $this->applyAddColumn($createStmt, $op, $definition);
        } elseif (self::optionSet($options, 'DROP') && self::optionSet($options, 'COLUMN')) {
            $this->applyDropColumn($createStmt, $op, $store, $definition);
        } elseif (self::optionSet($options, 'DROP') && !self::optionSet($options, 'COLUMN') && !self::optionSet($options, 'PRIMARY KEY') && !self::optionSet($options, 'FOREIGN') && !self::optionSet($options, 'INDEX') && !self::optionSet($options, 'KEY') && !self::optionSet($options, 'CONSTRAINT')) {
            $this->applyDropColumn($createStmt, $op, $store, $definition);
        } elseif (self::optionSet($options, 'MODIFY') || self::optionSet($options, 'MODIFY COLUMN')) {
            $this->applyModifyColumn($createStmt, $op, $definition);
        } elseif (self::optionSet($options, 'CHANGE') || self::optionSet($options, 'CHANGE COLUMN')) {
            $this->applyChangeColumn($createStmt, $op, $store, $definition);
        } elseif (self::optionSet($options, 'RENAME') && self::optionSet($options, 'TO') && !self::optionSet($options, 'INDEX') && !self::optionSet($options, 'KEY') && !self::optionSet($options, 'COLUMN')) {
            $this->applyRenameTable($op, $store);
        } elseif (self::optionSet($options, 'ADD') && self::optionSet($options, 'PRIMARY KEY')) {
            $this->applyAddPrimaryKey($createStmt, $op);
        } elseif (self::optionSet($options, 'DROP') && self::optionSet($options, 'PRIMARY KEY')) {
            $this->applyDropPrimaryKey($createStmt);
        } elseif (self::optionSet($options, 'ADD') && self::optionSet($options, 'FOREIGN')) {
            // foreign key constraints are metadata-only in ZTD
        } elseif (self::optionSet($options, 'DROP') && self::optionSet($options, 'FOREIGN')) {
            // foreign key constraints are metadata-only in ZTD
        } elseif (self::optionSet($options, 'RENAME') && self::optionSet($options, 'COLUMN')) {
            $this->applyRenameColumn($createStmt, $op, $store, $definition);
        } elseif (self::optionSet($options, 'ALTER') && (self::optionSet($options, 'SET DEFAULT') || self::optionSet($options, 'DROP DEFAULT'))) {
            // SET DEFAULT / DROP DEFAULT not fully supported
        } else {
            throw new UnsupportedSqlException(AlterOperation::build($op), 'ALTER TABLE');
        }
    }

    private function applyAddColumn(CreateStatement $createStmt, AlterOperation $op, TableDefinition $definition): void
    {
        if (!is_array($createStmt->fields)) {
            $createStmt->fields = [];
        }

        $columnDef = $this->buildColumnDefinition($op);
        if ($columnDef !== null) {
            $columnName = $this->normalizeColumnName($columnDef->name ?? '');

            if ($columnName !== '' && in_array($columnName, $definition->columns, true)) {
                throw new ColumnAlreadyExistsException(
                    $this->alterStatement->build(),
                    $this->tableName,
                    $columnName
                );
            }

            $createStmt->fields[] = $columnDef;
        }
    }

    private function applyDropColumn(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store, TableDefinition $definition): void
    {
        $columnName = $this->getColumnName($op);
        if ($columnName === null) {
            return;
        }

        if (!in_array($columnName, $definition->columns, true)) {
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

        $this->removeColumnFromStore($store, $columnName);
    }

    private function applyModifyColumn(CreateStatement $createStmt, AlterOperation $op, TableDefinition $definition): void
    {
        $columnDef = $this->buildColumnDefinition($op);
        if ($columnDef === null) {
            return;
        }

        $columnName = $this->normalizeColumnName($columnDef->name ?? '');
        if ($columnName === '' || !is_array($createStmt->fields)) {
            return;
        }

        if (!in_array($columnName, $definition->columns, true)) {
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

    private function applyChangeColumn(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store, TableDefinition $definition): void
    {
        $oldColumnName = $this->getColumnName($op);
        if ($oldColumnName === null) {
            return;
        }

        if (!in_array($oldColumnName, $definition->columns, true)) {
            throw new ColumnNotFoundException(
                $this->alterStatement->build(),
                $this->tableName,
                $oldColumnName
            );
        }

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

        if ($oldColumnName !== $newColumnName) {
            $this->renameColumnInStore($store, $oldColumnName, $newColumnName);
        }
    }

    private function applyRenameColumn(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store, TableDefinition $definition): void
    {
        $oldColumnName = $this->getColumnName($op);
        if ($oldColumnName === null) {
            return;
        }

        if (!in_array($oldColumnName, $definition->columns, true)) {
            throw new ColumnNotFoundException(
                $this->alterStatement->build(),
                $this->tableName,
                $oldColumnName
            );
        }

        $toValue = $op->options !== null ? $op->options->has('TO') : false;
        if (!is_string($toValue) || $toValue === '') {
            return;
        }
        $newColumnName = $this->normalizeColumnName($toValue);

        if (!is_array($createStmt->fields)) {
            return;
        }

        foreach ($createStmt->fields as $field) {
            if ($this->normalizeColumnName($field->name ?? '') === $oldColumnName) {
                $field->name = $newColumnName;
                break;
            }
        }

        if ($oldColumnName !== $newColumnName) {
            $this->renameColumnInStore($store, $oldColumnName, $newColumnName);
        }
    }

    private function applyRenameTable(AlterOperation $op, ShadowStore $store): void
    {
        $toValue = $op->options !== null ? $op->options->has('TO') : false;
        if (!is_string($toValue) || $toValue === '') {
            return;
        }

        $newName = $this->normalizeColumnName($toValue);

        $data = $store->get($this->tableName);
        $store->set($newName, $data);
        $store->set($this->tableName, []);

        $existingDef = $this->registry->get($this->tableName);
        if ($existingDef !== null) {
            $this->registry->unregister($this->tableName);
            $this->registry->register($newName, $existingDef);
        }

        $this->tableName = $newName;
    }

    private function applyAddPrimaryKey(CreateStatement $createStmt, AlterOperation $op): void
    {
        $keyDef = new CreateDefinition();
        $keyDef->key = new \PhpMyAdmin\SqlParser\Components\Key();
        $keyDef->key->type = 'PRIMARY KEY';
        $keyDef->key->columns = [];

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

    private function applyDropPrimaryKey(CreateStatement $createStmt): void
    {
        if (!is_array($createStmt->fields)) {
            return;
        }

        foreach ($createStmt->fields as $field) {
            if ($field->options !== null && self::optionSet($field->options, 'PRIMARY KEY')) {
                $field->options->remove('PRIMARY KEY');
            }
        }

        $createStmt->fields = array_values(array_filter(
            $createStmt->fields,
            fn ($field) => $field->key === null || $field->key->type !== 'PRIMARY KEY'
        ));
    }

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

        $tokens = is_array($op->unknown) ? $op->unknown : [];
        $typeStr = '';
        foreach ($tokens as $token) {
            $typeStr .= $token->token;
        }

        $defSql = "CREATE TABLE t (`$columnName` $typeStr)";
        $parser = new \PhpMyAdmin\SqlParser\Parser($defSql);
        if ($parser->statements === [] || !$parser->statements[0] instanceof CreateStatement) {
            return null;
        }

        $tempCreate = $parser->statements[0];
        if (!is_array($tempCreate->fields) || $tempCreate->fields === []) {
            return null;
        }

        return $tempCreate->fields[0];
    }

    private function buildColumnDefinitionFromUnknown(AlterOperation $op): ?CreateDefinition
    {
        $tokens = is_array($op->unknown) ? $op->unknown : [];
        if ($tokens === []) {
            return null;
        }

        $tokenStr = '';
        foreach ($tokens as $token) {
            $tokenStr .= $token->token;
        }

        $defSql = "CREATE TABLE t ($tokenStr)";
        $parser = new \PhpMyAdmin\SqlParser\Parser($defSql);
        if ($parser->statements === [] || !$parser->statements[0] instanceof CreateStatement) {
            return null;
        }

        $tempCreate = $parser->statements[0];
        if (!is_array($tempCreate->fields) || $tempCreate->fields === []) {
            return null;
        }

        return $tempCreate->fields[0];
    }

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

    private function normalizeColumnName(string $name): string
    {
        return str_replace('`', '', $name);
    }

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
     * Check whether the given OptionsArray has a specific option set.
     *
     * @param \PhpMyAdmin\SqlParser\Components\OptionsArray $options
     */
    private static function optionSet(\PhpMyAdmin\SqlParser\Components\OptionsArray $options, string $name): bool
    {
        return $options->has($name) !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
