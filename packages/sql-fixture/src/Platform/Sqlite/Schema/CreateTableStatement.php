<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Schema\Exception\ExpectedCreateTableException;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Locates the CREATE TABLE command and its column and constraint lists in a syntax tree.
 *
 * @visibility root
 */
final class CreateTableStatement
{
    /**
     * Returns the cmd node that holds a create_table clause.
     * @throws InvalidSqlException
     * @throws ExpectedCreateTableException
     */
    public function locate(Node $tree, string $sql): Node
    {
        $reader = new NodeReader();
        if ($reader->wordsOutsideParentheses($tree) === []) {
            throw new InvalidSqlException($sql, 'No statements found');
        }
        foreach ($tree->find('cmd') as $command) {
            if ($reader->child($command, 'create_table') !== null) {
                return $command;
            }
        }

        throw new ExpectedCreateTableException($sql);
    }

    /**
     * Returns each column name node paired with the constraint list that follows it.
     *
     * @return list<array{Node, Node}>
     */
    public function columns(Node $command): array
    {
        $reader = new NodeReader();
        $arguments = $reader->child($command, 'create_table_args');
        $list = $arguments === null ? null : $reader->child($arguments, 'columnlist');

        return $list === null ? [] : $this->pairs($list);
    }

    /**
     * Walks a left-recursive columnlist, pairing every columnname with its carglist.
     *
     * @return list<array{Node, Node}>
     */
    public function pairs(Node $columnlist): array
    {
        $pairs = [];
        $name = null;
        foreach ($columnlist->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if ($child->name === 'columnlist') {
                array_push($pairs, ...$this->pairs($child));
            } elseif ($child->name === 'columnname') {
                $name = $child;
            } elseif ($child->name === 'carglist' && $name !== null) {
                $pairs[] = [$name, $child];
                $name = null;
            }
        }

        return $pairs;
    }

    /**
     * Returns the table constraints declared after the columns.
     *
     * @return list<Node>
     */
    public function constraints(Node $command): array
    {
        $reader = new NodeReader();
        $arguments = $reader->child($command, 'create_table_args');
        $list = $arguments === null ? null : $reader->child($arguments, 'conslist_opt');

        return $list === null ? [] : $list->find('tcons');
    }
}
