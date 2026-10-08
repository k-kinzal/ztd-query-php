<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
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
 * @visibility MySqlMemory
 */
final class Syntax
{
    /**
     * The deprecated constructs written before the names of a statement, whose warnings come before those of leading dots.
     */
    public const HEAD = [
        \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::InsertDelayed, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ReplaceDelayed,
        \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Cache, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::NoCache,
        \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::CalcFoundRows,
    ];

    /**
     * Answers the deprecations MySQL 5.7 raises for names written after a leading dot, `.t` and `.t.c`, by the offset of the dot (verified on a live 5.7.44 server).
     *
     * @return array<int, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated>
     */
    public function dots(Node $tree): array
    {
        $found = [];
        foreach (['table_ident' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotTable, 'simple_ident_q' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotColumn] as $rule => $construct) {
            foreach ($tree->find($rule) as $node) {
                $first = $node->children[0] ?? null;
                if ($first instanceof \SqlParser\Lexer\Token && $first->text === '.') {
                    $found[$first->offset] = $construct;
                }
            }
        }
        ksort($found);

        return $found;
    }

    /**
     * Refuses PARSE_GCOL_EXPR, which the 5.7 grammar holds for the server to read generated columns with, as a syntax error at its start when a client sends it (verified on a live 5.7.44 server).
     *
     * @throws SqlError When the statement is PARSE_GCOL_EXPR
     */
    public function internal(\SqlSemantics\Statement\Statement $statement, string $text): void
    {
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\ParseGeneratedColumn) {
            throw new SqlError(StatementError::ParseError, StatementError::ParseError->message(mb_strcut(ltrim($text), 0, 80, 'UTF-8'), 1));
        }
    }

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

                throw new SqlError(StatementError::ParseError, StatementError::ParseError->message(mb_strcut(substr($statement, $offset), 0, 80, 'UTF-8'), $line));
            }
        }
    }

    /**
     * Refuses SHOW PARSE_TREE, which only a debug build of the server runs, as the parser of a release build does: a syntax error at PARSE_TREE.
     *
     * The server reads the whole statement first, so the problems it finds while it parses the
     * statement inside come before (verified on a live 8.4 server).
     *
     * @throws SqlError When the statement is SHOW PARSE_TREE
     */
    public function debugOnly(Node $tree, string $statement): void
    {
        $previous = null;
        foreach ($tree->tokens() as $token) {
            $shown = $previous?->name === 'SHOW';
            $previous = $token;
            if ($token->name === 'PARSE_TREE_SYM' && $shown) {
                $offset = $token->offset;
                $line = substr_count(substr($statement, 0, $offset), "\n") + 1;

                throw new SqlError(StatementError::ParseError, StatementError::ParseError->message(mb_strcut(substr($statement, $offset), 0, 80, 'UTF-8'), $line));
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
                throw DataError::WrongValue->error($form, $text);
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
     * Answers the parse error of a failure to read a statement; an attribute a generated column cannot have is ER_WRONG_USAGE, as the parser of the server refuses it (verified on a live 8.4 server).
     */
    public function error(Throwable $failure, string $statement): SqlError
    {
        $cause = $failure;
        while ($cause !== null && !$cause instanceof SyntaxException) {
            $cause = $cause->getPrevious();
        }
        if (!$cause instanceof SyntaxException && preg_match('/\AIncorrect usage of (.+) and generated column\z/', $failure->getMessage(), $usage) === 1) {
            return new SqlError(StatementError::WrongUsage, StatementError::WrongUsage->message($usage[1], 'generated column'), $failure);
        }
        if (!$cause instanceof SyntaxException) {
            return new SqlError(StatementError::ParseError, StatementError::ParseError->message('', 1), $failure);
        }
        $offset = $cause->token->offset;
        $line = substr_count(substr($statement, 0, $offset), "\n") + 1;
        $near = substr($statement, $offset);

        return new SqlError(StatementError::ParseError, StatementError::ParseError->message(mb_strcut($near, 0, 80, 'UTF-8'), $line), $failure);
    }
}
