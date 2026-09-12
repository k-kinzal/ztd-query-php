<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Alter;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Operation Applier.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class OperationApplier
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private AlterStatement $alterStatement, private TableDefinitionRegistry $registry, private string $tableName)
    {
    }
    /**
     * Apply a single ALTER operation to the CREATE statement.
     * @throws UnsupportedSqlException
     */
    public function applyOperation(CreateStatement $createStmt, AlterOperation $op, ShadowStore $store, TableDefinition $definition): string
    {
        $options = $op->options;
        if ($options->isEmpty()) {
            return $this->tableName;
        }

        $columnAction = (new ColumnAction())->detect($op);
        if ($columnAction !== null) {
            $columns = new ColumnAlteration($this->alterStatement, $this->tableName);
            match ($columnAction) {
                'add' => $columns->applyAddColumn($createStmt, $op, $definition),
                'drop' => $columns->applyDropColumn($createStmt, $op, $store, $definition),
                'modify' => $columns->applyModifyColumn($createStmt, $op, $definition),
                'change' => $columns->applyChangeColumn($createStmt, $op, $store, $definition),
            };
            return $this->tableName;
        }
        if (($options->has('RENAME') !== false) && ($options->has('TO') !== false) && !\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::hasAny($options, ['INDEX', 'KEY', 'COLUMN'])) {
            $this->applyRenameTable($op, $store);
        } elseif (($options->has('ADD') !== false) && ($options->has('PRIMARY KEY') !== false)) {
            (new PrimaryKeyAlteration())->applyAddPrimaryKey($createStmt, $op);
        } elseif (($options->has('DROP') !== false) && ($options->has('PRIMARY KEY') !== false)) {
            (new PrimaryKeyAlteration())->applyDropPrimaryKey($createStmt);
        } elseif (($options->has('FOREIGN') !== false) && \ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::hasAny($options, ['ADD', 'DROP'])) {
            return $this->tableName;
        } elseif (($options->has('RENAME') !== false) && ($options->has('COLUMN') !== false)) {
            (new ColumnAlteration($this->alterStatement, $this->tableName))->applyRenameColumn($createStmt, $op, $store, $definition);
        } elseif (($options->has('ALTER') !== false) && (\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::hasAny($options, ['SET DEFAULT', 'DROP DEFAULT']))) {
            return $this->tableName;
        } else {
            throw new UnsupportedSqlException(AlterOperation::build($op), 'ALTER TABLE');
        }
        return $this->tableName;
    }

    /**
     * Apply Rename Table for the supplied MySQL input.
     */
    public function applyRenameTable(AlterOperation $op, ShadowStore $store): void
    {
        $toValue = $op->options !== null ? $op->options->has('TO') : false;
        if (!is_string($toValue) || $toValue === '') {
            return;
        }

        $newName = (str_replace('`', '', $toValue));

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
}
