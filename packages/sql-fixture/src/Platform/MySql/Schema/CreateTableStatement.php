<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Schema\Exception\ExpectedCreateTableException;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Locates the CREATE TABLE statement and its declared elements in a syntax tree.
 *
 * @visibility root
 */
final class CreateTableStatement
{
    /**
     * Returns the create_table_stmt node of the parsed statement.
     * @throws InvalidSqlException
     * @throws ExpectedCreateTableException
     */
    public function locate(Node $tree, string $sql): Node
    {
        if ((new NodeReader())->wordsOutsideParentheses($tree) === []) {
            throw new InvalidSqlException($sql, 'No statements found');
        }

        $statements = $tree->find('create_table_stmt');
        if ($statements === []) {
            throw new ExpectedCreateTableException($sql);
        }

        return $statements[0];
    }

    /**
     * Returns the column definitions and table constraints declared between the parentheses.
     *
     * @return list<Node>
     */
    public function elements(Node $statement): array
    {
        $list = (new NodeReader())->child($statement, 'table_element_list');

        return $list === null ? [] : $list->find('table_element');
    }
}
