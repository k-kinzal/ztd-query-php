<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use SqlParser\Parser\SyntaxException;
use Throwable;

/**
 * Reports text the grammar refuses as the server does: ER_PARSE_ERROR near the text that follows the refused token.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_parse_error.
 *
 * @visibility MySqlMemory\Session
 */
final class Syntax
{
    /**
     * Answers the parse error of a failure to read a statement.
     */
    public function error(Throwable $failure, string $statement): SqlError
    {
        for ($cause = $failure; $cause !== null && !$cause instanceof SyntaxException; $cause = $cause->getPrevious()) {
        }
        if (!$cause instanceof SyntaxException) {
            return new SqlError(ErrorCode::ParseError, ErrorCode::ParseError->message('', 1), $failure);
        }
        $offset = $cause->token->offset;
        $line = substr_count(substr($statement, 0, $offset), "\n") + 1;
        $near = substr($statement, $offset);

        return new SqlError(ErrorCode::ParseError, ErrorCode::ParseError->message(mb_strcut($near, 0, 80, 'UTF-8'), $line), $failure);
    }
}
