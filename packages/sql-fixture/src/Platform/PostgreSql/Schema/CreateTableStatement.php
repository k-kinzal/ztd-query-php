<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

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
     * Returns the CreateStmt node of the parsed text.
     *
     * Other statements may stand beside it, a DROP TABLE before it for
     * instance, but a text declaring more than one table does not say which
     * of them was meant.
     * @throws InvalidSqlException
     * @throws ExpectedCreateTableException
     */
    public function locate(Node $tree, string $sql): Node
    {
        if ((new NodeReader())->wordsOutsideParentheses($tree) === []) {
            throw new InvalidSqlException($sql, 'No statements found');
        }

        $statements = $tree->find('CreateStmt');
        if ($statements === []) {
            throw new ExpectedCreateTableException($sql);
        }
        if (count($statements) > 1) {
            throw new InvalidSqlException($sql, 'More than one CREATE TABLE statement');
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
        $list = (new NodeReader())->child($statement, 'OptTableElementList');

        return $list === null ? [] : $list->find('TableElement');
    }
}
