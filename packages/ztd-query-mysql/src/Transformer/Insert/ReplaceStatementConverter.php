<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Insert;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Replace Statement Converter.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ReplaceStatementConverter
{
    /**
     * As Insert for the supplied MySQL input.
     * @throws UnsupportedSqlException
     */
    public function asInsert(string $sql): string
    {
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();
        if ($tokens === []) {
            throw new UnsupportedSqlException($sql, 'Expected REPLACE statement');
        }
        $token = $tokens[0];
        if (!$token->isKeyword('REPLACE')) {
            throw new UnsupportedSqlException($sql, 'Expected REPLACE statement');
        }

        return substr_replace($sql, 'INSERT', $token->offset, strlen($token->text));
    }
}
