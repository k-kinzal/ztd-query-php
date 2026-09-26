<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Schema\Exception\UnreadableTableNameException;
use SqlParser\Lexer\SourceException;
use SqlParser\MySql\MySqlParser;

/**
 * Writes the statement that reads the declaration of one table.
 *
 * The name is written into the statement and the statement is read back with
 * the grammar of the server, and it is issued only when the grammar read the
 * whole of it as one table's declaration and nothing else: no second
 * statement, no comment and no spacing of its own. A name that is not read
 * that way, a reserved word or a name written with a space in it, is written
 * as one quoted identifier and read back again.
 *
 * @visibility root
 */
final class ShowCreateTable
{
    /**
     * Keeps the grammar the written statement is read back with.
     */
    public function __construct(private readonly MySqlParser $parser = new MySqlParser())
    {
    }

    /**
     * Answers the statement that reads the declaration of the named table.
     * @throws UnreadableTableNameException
     */
    public function statement(string $tableName): string
    {
        $statement = $this->readable('SHOW CREATE TABLE ' . $tableName)
            ?? $this->readable('SHOW CREATE TABLE ' . $this->quoted($tableName));
        if ($statement === null) {
            throw new UnreadableTableNameException($tableName);
        }

        return $statement;
    }

    /**
     * Answers the statement as the grammar reads it back, or null when it is anything but one table's declaration.
     */
    public function readable(string $statement): ?string
    {
        try {
            $tree = $this->parser->parse($statement);
        } catch (SourceException) {
            return null;
        }
        $idents = $tree->find('table_ident');
        if (count($idents) !== 1) {
            return null;
        }
        $written = 'SHOW CREATE TABLE ';
        foreach ($idents[0]->tokens() as $token) {
            $written .= $token->text;
        }

        return $tree->toString() === $written ? $written : null;
    }

    /**
     * Writes a name as one identifier, doubling the quote the way the server reads it.
     */
    public function quoted(string $tableName): string
    {
        return '`' . str_replace('`', '``', $tableName) . '`';
    }
}
