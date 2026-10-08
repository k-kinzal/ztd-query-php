<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Value\Temporal;
use SqlParser\Parser\Node;
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
     * Refuses a parameter marker outside a prepared statement, as the parser of the server does.
     *
     * @throws SqlError When the statement holds a parameter marker
     */
    public function markers(Node $tree, string $statement): void
    {
        foreach ($tree->tokens() as $token) {
            if ($token->text === '?' && $token->name === 'PARAM_MARKER') {
                $offset = $token->offset;
                $line = substr_count(substr($statement, 0, $offset), "\n") + 1;

                throw new SqlError(ErrorCode::ParseError, ErrorCode::ParseError->message(mb_strcut(substr($statement, $offset), 0, 80, 'UTF-8'), $line));
            }
        }
    }

    /**
     * Refuses a DATE, TIME or TIMESTAMP literal whose text is no value of its form (ER_WRONG_VALUE).
     *
     * The parser of the server reads the literal, so the error comes before any name of the
     * statement is resolved. NO_ZERO_DATE and NO_ZERO_IN_DATE refuse zero dates and zero parts.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html.
     *
     * @throws SqlError When a temporal literal holds no value of its form
     */
    public function temporals(Node $tree, SqlModes $modes): void
    {
        foreach ($tree->find('temporal_literal') as $literal) {
            $tokens = $literal->tokens();
            if (count($tokens) !== 2) {
                continue;
            }
            $form = match ($tokens[0]->name) {
                'DATE_SYM' => 'DATE',
                'TIME_SYM' => 'TIME',
                default => 'DATETIME',
            };
            $text = $this->unquote($tokens[1]->text, !$modes->has('NO_BACKSLASH_ESCAPES'));
            if (Temporal::literal($form, $text, 6, $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE')) === null) {
                throw ErrorCode::WrongValue->error($form, $text);
            }
        }
    }

    /**
     * Answers the text a quoted string token writes: the quotes removed, a doubled quote and the escape sequences read.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/string-literals.html.
     */
    public function unquote(string $token, bool $backslashes): string
    {
        $quote = $token[0] ?? '';
        if (($quote !== "'" && $quote !== '"') || strlen($token) < 2) {
            return $token;
        }
        $body = str_replace($quote . $quote, $quote, substr($token, 1, -1));
        if (!$backslashes) {
            return $body;
        }
        $escapes = ['0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1A", '%' => '\\%', '_' => '\\_'];

        return (string) preg_replace_callback('/\\\\(.)/s', static fn (array $match): string => $escapes[$match[1]] ?? $match[1], $body);
    }

    /**
     * Answers the parse error of a failure to read a statement.
     */
    public function error(Throwable $failure, string $statement): SqlError
    {
        $cause = $failure;
        while ($cause !== null && !$cause instanceof SyntaxException) {
            $cause = $cause->getPrevious();
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
