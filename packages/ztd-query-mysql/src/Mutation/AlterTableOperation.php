<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\Key;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Token;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlComponentSql;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies one ALTER TABLE operation to a table's reconstructed declaration.
 *
 * The declaration is altered in place, because the parser's own statement
 * object is what will be written back out. The table name is passed in and
 * answered back rather than held, because RENAME TO changes it and everything
 * after that operation is about the new name.
 */
final class AlterTableOperation
{
    /**
     * @param AlterStatement $statement Statement being simulated, for anything it refuses
     * @param TableDefinitionRegistry $registry What each table declares
     * @param AlterTableColumn $columns Reads the column an operation is about
     * @param AlterTableRows $rows Carries a column change through to the rows
     * @param MySqlComponentSql $components Asks the parser to write a piece of a statement back out
     */
    public function __construct(
        private readonly AlterStatement $statement,
        private readonly TableDefinitionRegistry $registry,
        private readonly AlterTableColumn $columns = new AlterTableColumn(),
        private readonly AlterTableRows $rows = new AlterTableRows(),
        private readonly MySqlComponentSql $components = new MySqlComponentSql(),
    ) {
    }

    /**
     * Applies one operation, and answers the name the table has afterwards.
     *
     * An operation ZTD models as metadata only -- a foreign key, a column
     * default -- changes the declaration the registry already holds, so there
     * is nothing here left to do for it.
     *
     * @param CreateStatement $createStmt Declaration being altered, changed in place
     * @param AlterOperation $op Operation to apply
     * @param ShadowStore $store Shadow holding the table's rows
     * @param TableDefinition $definition What the table declared before the statement
     * @param string $tableName Table the operation is against
     *
     * @return string The name the table has once the operation has been applied
     *
     * @throws UnsupportedSqlException When the operation asks for something ZTD cannot simulate
     * @throws ColumnAlreadyExistsException When it adds a column the table already has
     * @throws ColumnNotFoundException When it changes a column the table does not have
     */
    public function applyTo(
        CreateStatement $createStmt,
        AlterOperation $op,
        ShadowStore $store,
        TableDefinition $definition,
        string $tableName,
    ): string {
        $options = $op->options;
        if ($options->isEmpty()) {
            return $tableName;
        }

        $set = fn (string $name): bool => $this->columns->optionIsSet($options, $name);
        $namesAnObject = $set('PRIMARY KEY') || $set('FOREIGN') || $set('INDEX') || $set('KEY')
            || $set('UNIQUE') || $set('FULLTEXT') || $set('SPATIAL') || $set('CONSTRAINT') || $set('PARTITION');

        if ($set('ADD') && $set('COLUMN')) {
            $this->addColumn($createStmt, $op, $definition, $tableName);
        } elseif ($set('ADD') && $set('PRIMARY KEY')) {
            $this->addPrimaryKey($createStmt, $op);
        } elseif ($set('ADD') && !$set('COLUMN') && !$namesAnObject && !$this->columns->mentionsUnsupported($op)) {
            $this->addColumn($createStmt, $op, $definition, $tableName);
        } elseif ($set('DROP') && $set('COLUMN')) {
            $this->dropColumn($createStmt, $op, $store, $definition, $tableName);
        } elseif ($set('DROP') && $set('PRIMARY KEY')) {
            $this->dropPrimaryKey($createStmt);
        } elseif ($set('DROP') && !$set('COLUMN') && !$set('FOREIGN') && !$set('INDEX') && !$set('KEY')
            && !$set('CONSTRAINT')) {
            $this->dropColumn($createStmt, $op, $store, $definition, $tableName);
        } elseif ($set('MODIFY') || $set('MODIFY COLUMN')) {
            $this->modifyColumn($createStmt, $op, $definition, $tableName);
        } elseif ($set('CHANGE') || $set('CHANGE COLUMN')) {
            $this->changeColumn($createStmt, $op, $store, $definition, $tableName);
        } elseif ($set('RENAME') && $set('TO') && !$set('INDEX') && !$set('KEY') && !$set('COLUMN')) {
            return $this->renameTable($op, $store, $tableName);
        } elseif ($set('RENAME') && $set('COLUMN')) {
            $this->renameColumn($createStmt, $op, $store, $definition, $tableName);
        } elseif (($set('ADD') || $set('DROP')) && $set('FOREIGN')) {
            return $tableName;
        } elseif ($set('ALTER') && ($set('SET DEFAULT') || $set('DROP DEFAULT'))) {
            return $tableName;
        } else {
            throw new UnsupportedSqlException($this->components->alterOperation($op, $this->statementSql()), 'ALTER TABLE');
        }

        return $tableName;
    }

    /**
     * Adds a column to the declaration.
     *
     * @param CreateStatement $createStmt Declaration being altered, changed in place
     * @param AlterOperation $op Operation to apply
     * @param TableDefinition $definition What the table declared before the statement
     * @param string $tableName Table the operation is against
     *
     * @throws ColumnAlreadyExistsException When the table already has that column
     */
    public function addColumn(
        CreateStatement $createStmt,
        AlterOperation $op,
        TableDefinition $definition,
        string $tableName,
    ): void {
        if (!is_array($createStmt->fields)) {
            $createStmt->fields = [];
        }

        $columnDef = $this->columns->definitionIn($op);
        if ($columnDef === null) {
            return;
        }

        $columnName = $this->columns->withoutQuotes($columnDef->name ?? '');
        if ($columnName !== '' && in_array($columnName, $definition->columns, true)) {
            throw new ColumnAlreadyExistsException($this->statementSql(), $tableName, $columnName);
        }

        $createStmt->fields[] = $columnDef;
    }

    /**
     * Drops a column from the declaration, and from every row of the table.
     *
     * @param CreateStatement $createStmt Declaration being altered, changed in place
     * @param AlterOperation $op Operation to apply
     * @param ShadowStore $store Shadow holding the table's rows
     * @param TableDefinition $definition What the table declared before the statement
     * @param string $tableName Table the operation is against
     *
     * @throws ColumnNotFoundException When the table has no such column
     */
    public function dropColumn(
        CreateStatement $createStmt,
        AlterOperation $op,
        ShadowStore $store,
        TableDefinition $definition,
        string $tableName,
    ): void {
        $columnName = $this->columns->nameIn($op);
        if ($columnName === null) {
            return;
        }
        $this->assertDeclared($columnName, $definition, $tableName);

        if (!is_array($createStmt->fields)) {
            return;
        }

        $createStmt->fields = array_values(array_filter(
            $createStmt->fields,
            fn (CreateDefinition $field): bool => $this->columns->withoutQuotes($field->name ?? '') !== $columnName,
        ));

        $this->rows->removeColumn($store, $tableName, $columnName);
    }

    /**
     * Redeclares a column, keeping the name it already has.
     *
     * @param CreateStatement $createStmt Declaration being altered, changed in place
     * @param AlterOperation $op Operation to apply
     * @param TableDefinition $definition What the table declared before the statement
     * @param string $tableName Table the operation is against
     *
     * @throws ColumnNotFoundException When the table has no such column
     */
    public function modifyColumn(
        CreateStatement $createStmt,
        AlterOperation $op,
        TableDefinition $definition,
        string $tableName,
    ): void {
        $columnDef = $this->columns->definitionIn($op);
        if ($columnDef === null) {
            return;
        }

        $columnName = $this->columns->withoutQuotes($columnDef->name ?? '');
        if ($columnName === '' || !is_array($createStmt->fields)) {
            return;
        }
        $this->assertDeclared($columnName, $definition, $tableName);

        $this->replaceField($createStmt, $columnName, $columnDef);
    }

    /**
     * Redeclares a column, name and all, and carries its values over.
     *
     * @param CreateStatement $createStmt Declaration being altered, changed in place
     * @param AlterOperation $op Operation to apply
     * @param ShadowStore $store Shadow holding the table's rows
     * @param TableDefinition $definition What the table declared before the statement
     * @param string $tableName Table the operation is against
     *
     * @throws ColumnNotFoundException When the table has no such column
     */
    public function changeColumn(
        CreateStatement $createStmt,
        AlterOperation $op,
        ShadowStore $store,
        TableDefinition $definition,
        string $tableName,
    ): void {
        $oldColumnName = $this->columns->nameIn($op);
        if ($oldColumnName === null) {
            return;
        }
        $this->assertDeclared($oldColumnName, $definition, $tableName);

        $newColumnDef = $this->columns->redefinitionIn($op);
        if ($newColumnDef === null || !is_array($createStmt->fields)) {
            return;
        }

        $this->replaceField($createStmt, $oldColumnName, $newColumnDef);

        $newColumnName = $this->columns->withoutQuotes($newColumnDef->name ?? '');
        if ($oldColumnName !== $newColumnName) {
            $this->rows->renameColumn($store, $tableName, $oldColumnName, $newColumnName);
        }
    }

    /**
     * Renames a column, leaving how it is declared alone.
     *
     * @param CreateStatement $createStmt Declaration being altered, changed in place
     * @param AlterOperation $op Operation to apply
     * @param ShadowStore $store Shadow holding the table's rows
     * @param TableDefinition $definition What the table declared before the statement
     * @param string $tableName Table the operation is against
     *
     * @throws ColumnNotFoundException When the table has no such column
     */
    public function renameColumn(
        CreateStatement $createStmt,
        AlterOperation $op,
        ShadowStore $store,
        TableDefinition $definition,
        string $tableName,
    ): void {
        $oldColumnName = $this->columns->nameIn($op);
        if ($oldColumnName === null) {
            return;
        }
        $this->assertDeclared($oldColumnName, $definition, $tableName);

        $newColumnName = $this->renamedTo($op);
        if ($newColumnName === null || !is_array($createStmt->fields)) {
            return;
        }

        foreach ($createStmt->fields as $field) {
            if ($this->columns->withoutQuotes($field->name ?? '') === $oldColumnName) {
                $field->name = $newColumnName;
                break;
            }
        }

        if ($oldColumnName !== $newColumnName) {
            $this->rows->renameColumn($store, $tableName, $oldColumnName, $newColumnName);
        }
    }

    /**
     * Moves a table's rows and its declaration to the name it now has.
     *
     * The old name is left holding no rows rather than left holding the ones
     * it had: after a rename there is no table there.
     *
     * @param AlterOperation $op Operation to apply
     * @param ShadowStore $store Shadow holding the table's rows
     * @param string $tableName Table the operation is against
     *
     * @return string The name the table has afterwards
     */
    public function renameTable(AlterOperation $op, ShadowStore $store, string $tableName): string
    {
        $newName = $this->renamedTo($op);
        if ($newName === null) {
            return $tableName;
        }

        $store->set($newName, $store->get($tableName));
        $store->set($tableName, []);

        $existing = $this->registry->get($tableName);
        if ($existing !== null) {
            $this->registry->unregister($tableName);
            $this->registry->register($newName, $existing);
        }

        return $newName;
    }

    /**
     * Adds a primary key over the columns the operation names.
     *
     * @param CreateStatement $createStmt Declaration being altered, changed in place
     * @param AlterOperation $op Operation to apply
     */
    public function addPrimaryKey(CreateStatement $createStmt, AlterOperation $op): void
    {
        $key = new Key();
        $key->type = 'PRIMARY KEY';
        $key->columns = [];
        foreach (is_array($op->unknown) ? $op->unknown : [] as $token) {
            if ($token->type === Token::TYPE_SYMBOL) {
                $key->columns[] = ['name' => $this->columns->withoutQuotes(is_string($token->value) ? $token->value : '')];
            }
        }

        $keyDef = new CreateDefinition();
        $keyDef->key = $key;

        if (!is_array($createStmt->fields)) {
            $createStmt->fields = [];
        }
        $createStmt->fields[] = $keyDef;
    }

    /**
     * Takes the primary key off, however it was declared.
     *
     * A key of one column is written on the column, and a key of several is
     * written on its own, so both spellings have to be taken off.
     *
     * @param CreateStatement $createStmt Declaration being altered, changed in place
     */
    public function dropPrimaryKey(CreateStatement $createStmt): void
    {
        if (!is_array($createStmt->fields)) {
            return;
        }

        foreach ($createStmt->fields as $field) {
            if ($field->options !== null && $this->columns->optionIsSet($field->options, 'PRIMARY KEY')) {
                $field->options->remove('PRIMARY KEY');
            }
        }

        $createStmt->fields = array_values(array_filter(
            $createStmt->fields,
            static fn (CreateDefinition $field): bool => $field->key === null || $field->key->type !== 'PRIMARY KEY',
        ));
    }

    /**
     * Answers the name an operation renames something to.
     *
     * @param AlterOperation $op Operation to read
     *
     * @return string|null The new name, or null where the operation names none
     */
    public function renamedTo(AlterOperation $op): ?string
    {
        $to = $op->options !== null ? $op->options->has('TO') : false;
        if (!is_string($to) || $to === '') {
            return null;
        }

        return $this->columns->withoutQuotes($to);
    }

    /**
     * Refuses an operation against a column the table does not declare.
     *
     * @param string $columnName Column the operation is about
     * @param TableDefinition $definition What the table declared before the statement
     * @param string $tableName Table the operation is against
     *
     * @throws ColumnNotFoundException When the table has no such column
     */
    public function assertDeclared(string $columnName, TableDefinition $definition, string $tableName): void
    {
        if (!in_array($columnName, $definition->columns, true)) {
            throw new ColumnNotFoundException($this->statementSql(), $tableName, $columnName);
        }
    }

    /**
     * Answers the statement as it was written, for a refusal to name.
     *
     * The parser gives a statement's text back by consuming the options it
     * read off it, so asking costs the statement its options. Nothing asks
     * unless it is already refusing, and a refused statement is not applied.
     *
     * @return string The statement, as the parser writes it back
     */
    public function statementSql(): string
    {
        return $this->statement->build();
    }

    /**
     * Writes a new declaration over the one a column already has.
     *
     * @param CreateStatement $createStmt Declaration being altered, changed in place
     * @param string $columnName Column to replace
     * @param CreateDefinition $columnDef Declaration to put in its place
     */
    public function replaceField(CreateStatement $createStmt, string $columnName, CreateDefinition $columnDef): void
    {
        if (!is_array($createStmt->fields)) {
            return;
        }

        foreach ($createStmt->fields as $index => $field) {
            if ($this->columns->withoutQuotes($field->name ?? '') === $columnName) {
                $createStmt->fields[$index] = $columnDef;
                break;
            }
        }
    }
}
