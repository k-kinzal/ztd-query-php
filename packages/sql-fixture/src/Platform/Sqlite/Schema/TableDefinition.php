<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Schema\Exception\MissingColumnDefinitionsException;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Reads the table name, columns and primary key from a CREATE TABLE command node.
 *
 * @visibility root
 */
final class TableDefinition
{
    /**
     * Reads the declared table identifier without its database qualifier.
     * @throws InvalidSqlException
     */
    public function extractTableName(Node $command, string $sql): string
    {
        $reader = new NodeReader();
        $create = $reader->child($command, 'create_table');
        $qualifier = $create === null ? null : $reader->child($create, 'dbnm');
        $nameNode = $qualifier !== null && !$qualifier->isEmpty() ? $reader->child($qualifier, 'nm') : ($create === null ? null : $reader->child($create, 'nm'));
        $token = $nameNode === null ? null : $reader->firstToken($nameNode);
        $name = $token === null ? '' : (new Identifier())->decode($token);
        if ($name === '') {
            throw new InvalidSqlException($sql, 'Could not extract table name');
        }

        return $name;
    }

    /**
     * @return array<string, ColumnDefinition>
     * @throws MissingColumnDefinitionsException
     */
    public function extractColumns(Node $command, string $tableName): array
    {
        $columns = [];
        $primaryKeys = $this->extractPrimaryKeys($command);
        foreach ((new CreateTableStatement())->columns($command) as [$columnname, $carglist]) {
            $column = (new ColumnParser())->parseColumnDefinition($columnname, $carglist, $primaryKeys);
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
    public function extractPrimaryKeys(Node $command): array
    {
        $reader = new NodeReader();
        $primaryKeys = [];
        foreach ((new CreateTableStatement())->columns($command) as [$columnname, $carglist]) {
            $nameNode = $reader->child($columnname, 'nm');
            $token = $nameNode === null ? null : $reader->firstToken($nameNode);
            if ($token !== null && (new ColumnConstraints())->read($carglist)->primaryKey) {
                $primaryKeys[] = (new Identifier())->decode($token);
            }
        }
        foreach ((new CreateTableStatement())->constraints($command) as $constraint) {
            if ($reader->token($constraint, 'PRIMARY') === null) {
                continue;
            }
            $sortlist = $reader->child($constraint, 'sortlist');
            if ($sortlist !== null) {
                array_push($primaryKeys, ...$this->keyColumns($sortlist));
            }
        }

        return array_values(array_unique($primaryKeys));
    }

    /**
     * Reads the column names of a left-recursive sort list in text order, skipping key parts that are expressions.
     *
     * @return list<string>
     */
    public function keyColumns(Node $sortlist): array
    {
        $names = [];
        foreach ($sortlist->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if ($child->name === 'sortlist') {
                array_push($names, ...$this->keyColumns($child));
                continue;
            }
            $tokens = $child->tokens();
            if ($child->name === 'expr' && count($tokens) === 1) {
                $names[] = (new Identifier())->decode($tokens[0]);
            }
        }

        return $names;
    }
}
