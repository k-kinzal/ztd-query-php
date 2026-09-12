<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the My Sql Upsert Expression Parser contract for MySQL.
 */
final class MySqlUpsertExpressionParser
{
    /**
     * Parse for the supplied MySQL input.
     * @throws UnsupportedSqlException
     */
    public function parse(string $sql, string $tableName, ?string $incomingAlias = null): UpsertExpression
    {
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = (new Parsing\Upsert\ExpressionReader())->parseOr($sql, $tableName, $incomingAlias, $tokens, $index);
        if ($index !== count($tokens)) {
            throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression'));
        }

        return $expression;
    }

    /**
     * Parse If Supported for the supplied MySQL input.
     */
    public function parseIfSupported(string $sql, string $tableName, ?string $incomingAlias = null): ?UpsertExpression
    {
        try {
            return $this->parse($sql, $tableName, $incomingAlias);
        } catch (UnsupportedSqlException) {
            return null;
        }
    }

}
