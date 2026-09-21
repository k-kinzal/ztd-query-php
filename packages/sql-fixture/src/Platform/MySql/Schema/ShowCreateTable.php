<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlParser\Lexer\SourceException;
use SqlParser\MySql\MySqlParser;

/**
 * Writes the statement that reads the declaration of one table.
 *
 * The name is written into the statement and the statement is read back with
 * the grammar of the server, so what is issued is what the grammar accepted
 * as one table's declaration. A name the grammar does not read that way, a
 * reserved word or a name written with a space in it, is written as one
 * quoted identifier rather than taken apart.
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
     */
    public function statement(string $tableName): string
    {
        $quoted = 'SHOW CREATE TABLE ' . $this->quoted($tableName);

        return $this->readable('SHOW CREATE TABLE ' . $tableName) ?? $this->readable($quoted) ?? $quoted;
    }

    /**
     * Answers the statement as the grammar reads it back, or null when it does not name one table.
     */
    public function readable(string $statement): ?string
    {
        try {
            $tree = $this->parser->parse($statement);
        } catch (SourceException) {
            return null;
        }

        return count($tree->find('table_ident')) === 1 ? $tree->toString() : null;
    }

    /**
     * Writes a name as one identifier, doubling the quote the way the server reads it.
     */
    public function quoted(string $tableName): string
    {
        return '`' . str_replace('`', '``', $tableName) . '`';
    }
}
