<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Schema\Exception\MissingColumnDefinitionsException;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Reads the table name, columns and primary key from a create_table_stmt node.
 *
 * @visibility root
 */
final class TableDefinition
{
    /**
     * Reads the declared table identifier without its database qualifier.
     * @throws InvalidSqlException
     */
    public function extractTableName(Node $statement, string $sql): string
    {
        $tableIdent = (new NodeReader())->child($statement, 'table_ident');
        $idents = $tableIdent === null ? [] : $tableIdent->find('ident');
        $last = end($idents);
        $name = $last === false ? null : (new Identifier())->decode($last);
        if ($name === null || $name === '') {
            throw new InvalidSqlException($sql, 'Table name not found');
        }

        return $name;
    }

    /**
     * @return array<string, ColumnDefinition>
     * @throws MissingColumnDefinitionsException
     */
    public function extractColumns(Node $statement, string $sql, string $tableName): array
    {
        $columns = [];
        $primaryKeys = $this->extractPrimaryKeys($statement);
        foreach ((new CreateTableStatement())->elements($statement) as $element) {
            $columnDef = (new NodeReader())->child($element, 'column_def');
            if ($columnDef === null) {
                continue;
            }
            $column = (new ColumnParser())->parseColumnDefinition($columnDef, $sql, $primaryKeys);
            if ($column !== null) {
                $columns[$column->name] = $column;
            }
        }
        if ($columns === []) {
            throw new MissingColumnDefinitionsException($tableName);
        }

        return $columns;
    }

    /**
     * Collects the primary key columns declared on columns and as a table constraint.
     *
     * @return list<string>
     */
    public function extractPrimaryKeys(Node $statement): array
    {
        $reader = new NodeReader();
        $primaryKeys = [];
        foreach ((new CreateTableStatement())->elements($statement) as $element) {
            $columnDef = $reader->child($element, 'column_def');
            if ($columnDef !== null) {
                $ident = $reader->child($columnDef, 'ident');
                $fieldDef = $reader->child($columnDef, 'field_def');
                $name = $ident === null ? null : (new Identifier())->decode($ident);
                if ($name !== null && $name !== '' && $fieldDef !== null && (new ColumnAttributes())->read($fieldDef)->primaryKey) {
                    $primaryKeys[] = $name;
                }
                continue;
            }
            $constraint = $reader->child($element, 'table_constraint_def');
            $keyType = $constraint === null ? null : $reader->child($constraint, 'constraint_key_type');
            if ($constraint === null || $keyType === null || $reader->token($keyType, 'PRIMARY_SYM') === null) {
                continue;
            }
            foreach ($constraint->find('key_part') as $part) {
                $ident = $reader->child($part, 'ident');
                $name = $ident === null ? null : (new Identifier())->decode($ident);
                if ($name !== null && $name !== '') {
                    $primaryKeys[] = $name;
                }
            }
        }

        return array_values(array_unique($primaryKeys));
    }
}
