<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Alter;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Column Alteration.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ColumnAlteration
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private AlterStatement $alterStatement, private string $tableName)
    {
    }
    /**
     * Apply Add Column for the supplied MySQL input.
     * @throws ColumnAlreadyExistsException
     */
    public function applyAddColumn(CreateStatement $createStmt, AlterOperation $op, TableDefinition $definition): void
    {
        if (!is_array($createStmt->fields)) {
            $createStmt->fields = [];
        }

        $columnDef = (new ColumnDefinitionParser())->buildColumnDefinition($op);
        if ($columnDef !== null) {
            $columnName = (str_replace('`', '', $columnDef->name ?? ''));

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

    /**
     * Apply Drop Column for the supplied MySQL input.
     * @throws ColumnNotFoundException
     */
    public function applyDropColumn(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store, TableDefinition $definition): void
    {
        $columnName = (new ColumnDefinitionParser())->getColumnName($op);
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
            fn ($field) => (str_replace('`', '', $field->name ?? '')) !== $columnName
        ));

        (new StoredColumns($this->tableName))->removeColumnFromStore($store, $columnName);
    }

    /**
     * Apply Modify Column for the supplied MySQL input.
     * @throws ColumnNotFoundException
     */
    public function applyModifyColumn(CreateStatement $createStmt, AlterOperation $op, TableDefinition $definition): void
    {
        $columnDef = (new ColumnDefinitionParser())->buildColumnDefinition($op);
        if ($columnDef === null) {
            return;
        }

        $columnName = (str_replace('`', '', $columnDef->name ?? ''));
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
            if ((str_replace('`', '', $field->name ?? '')) === $columnName) {
                $createStmt->fields[$i] = $columnDef;
                break;
            }
        }
    }

    /**
     * Apply Change Column for the supplied MySQL input.
     * @throws ColumnNotFoundException
     */
    public function applyChangeColumn(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store, TableDefinition $definition): void
    {
        $oldColumnName = (new ColumnDefinitionParser())->getColumnName($op);
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

        $newColumnDef = (new ColumnDefinitionParser())->buildColumnDefinitionFromUnknown($op);
        if ($newColumnDef === null) {
            return;
        }

        $newColumnName = (str_replace('`', '', $newColumnDef->name ?? ''));

        if (!is_array($createStmt->fields)) {
            return;
        }

        foreach ($createStmt->fields as $i => $field) {
            if ((str_replace('`', '', $field->name ?? '')) === $oldColumnName) {
                $createStmt->fields[$i] = $newColumnDef;
                break;
            }
        }

        if ($oldColumnName !== $newColumnName) {
            (new StoredColumns($this->tableName))->renameColumnInStore($store, $oldColumnName, $newColumnName);
        }
    }

    /**
     * Apply Rename Column for the supplied MySQL input.
     * @throws ColumnNotFoundException
     */
    public function applyRenameColumn(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store, TableDefinition $definition): void
    {
        $oldColumnName = (new ColumnDefinitionParser())->getColumnName($op);
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
        $newColumnName = (str_replace('`', '', $toValue));

        if (!is_array($createStmt->fields)) {
            return;
        }

        foreach ($createStmt->fields as $field) {
            if ((str_replace('`', '', $field->name ?? '')) === $oldColumnName) {
                $field->name = $newColumnName;
                break;
            }
        }

        if ($oldColumnName !== $newColumnName) {
            (new StoredColumns($this->tableName))->renameColumnInStore($store, $oldColumnName, $newColumnName);
        }
    }
}
