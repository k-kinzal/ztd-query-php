<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Parses supported upsert expressions with SQLite operator precedence.
 */
final class SqliteUpsertExpressionParser
{
    /**
     * Parses the supplied SQL into its supported structured representation.
     * @throws UnsupportedSqlException
     */
    public function parse(string $sql, string $tableName): UpsertExpression
    {
        $cursor = new Parsing\Upsert\ExpressionCursor(
            $sql,
            $tableName,
            SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->significantTokens(),
        );
        $expression = (new Parsing\Upsert\LogicalExpressionParser($cursor))->parseOr();
        if ($cursor->index !== count($cursor->tokens)) {
            throw (new Parsing\Upsert\ExpressionTokenDecoder($sql))->unsupported();
        }

        return $expression;
    }

    /**
     * Returns the parsed expression, or null for unsupported SQLite syntax.
     */
    public function parseIfSupported(string $sql, string $tableName): ?UpsertExpression
    {
        try {
            return $this->parse($sql, $tableName);
        } catch (UnsupportedSqlException) {
            return null;
        }
    }

}
