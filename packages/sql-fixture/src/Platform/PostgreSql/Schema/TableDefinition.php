<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Schema\Exception\MissingColumnDefinitionsException;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Reads the table name, columns and primary key from a CreateStmt node.
 *
 * @visibility root
 */
final class TableDefinition
{
    /**
     * Reads the declared table identifier without its schema qualifier.
     * @throws InvalidSqlException
     */
    public function extractTableName(Node $statement, string $sql): string
    {
        $qualifiedName = (new NodeReader())->child($statement, 'qualified_name');
        $tokens = $qualifiedName === null ? [] : $qualifiedName->tokens();
        $last = end($tokens);
        $name = $last === false ? '' : (new Identifier())->decode($last);
        if ($name === '') {
            throw new InvalidSqlException($sql, 'Could not extract table name');
        }

        return $name;
    }

    /**
     * @return array<string, ColumnDefinition>
     * @throws MissingColumnDefinitionsException
     */
    public function extractColumns(Node $statement, string $tableName): array
    {
        $columns = [];
        $primaryKeys = $this->extractPrimaryKeys($statement);
        foreach ((new CreateTableStatement())->elements($statement) as $element) {
            $columnDef = (new NodeReader())->child($element, 'columnDef');
            if ($columnDef === null) {
                continue;
            }
            $column = (new ColumnParser())->parseColumnDefinition($columnDef, $primaryKeys);
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
     * The columns a covering index INCLUDEs are not part of the key, so only
     * the list the constraint names directly is read.
     *
     * @return list<string>
     */
    public function extractPrimaryKeys(Node $statement): array
    {
        $reader = new NodeReader();
        $primaryKeys = [];
        foreach ((new CreateTableStatement())->elements($statement) as $element) {
            $columnDef = $reader->child($element, 'columnDef');
            if ($columnDef !== null) {
                $nameToken = $reader->firstToken($columnDef);
                if ($nameToken !== null && (new ColumnConstraints())->read($columnDef)->primaryKey) {
                    $primaryKeys[] = (new Identifier())->decode($nameToken);
                }
                continue;
            }
            $constraint = $reader->child($element, 'TableConstraint');
            $constraintElem = $constraint === null ? null : $reader->child($constraint, 'ConstraintElem');
            if ($constraintElem === null || $reader->token($constraintElem, 'PRIMARY') === null) {
                continue;
            }
            $columnList = $reader->child($constraintElem, 'columnList');
            foreach ($columnList === null ? [] : $columnList->find('columnElem') as $columnElem) {
                $token = $reader->firstToken($columnElem);
                if ($token !== null) {
                    $primaryKeys[] = (new Identifier())->decode($token);
                }
            }
        }

        return array_values(array_unique($primaryKeys));
    }
}
