<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads what follows `@` in MySQL: a user variable, a host name or a system variable.
 *
 * After one `@` the next word is a host name, so that `user@localhost` reads
 * as a whole. After `@@` the next word is a keyword or identifier naming a
 * system variable, possibly qualified with `global.` or `session.`.
 *
 * @visibility root
 */
final class VariableScanner
{
    /**
     * Reads the `@` at the cursor and decides how the next token is read.
     *
     * @param Scan $scan The tokenization in progress, positioned on the at sign
     *
     * @return Lexeme The lexeme for `@`
     */
    public function at(Scan $scan): Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $cursor->take(1);
        $next = $cursor->peek();
        $scan->next = match (true) {
            $next === '@' => LexerState::SystemVariable,
            $next === "'", $next === '"', $next === '`' => LexerState::Start,
            default => LexerState::Hostname,
        };

        return $scan->lexeme('@', $start);
    }

    /**
     * Reads the host name after `@`, which may be empty.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme The LEX_HOSTNAME lexeme
     */
    public function hostname(Scan $scan): Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $cursor->match('[A-Za-z0-9._$]*');
        $scan->next = LexerState::Start;

        return $scan->lexeme('LEX_HOSTNAME', $start);
    }

    /**
     * Reads the second `@` of a system variable reference.
     *
     * @param Scan $scan The tokenization in progress, positioned on the second at sign
     *
     * @return Lexeme The lexeme for `@`
     */
    public function systemVariable(Scan $scan): Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $cursor->take(1);
        $scan->next = $cursor->peek() === '`' ? LexerState::Start : LexerState::IdentifierOrKeyword;

        return $scan->lexeme('@', $start);
    }

    /**
     * Reads the name after `@@`, as a keyword when it is one.
     *
     * @param Scan $scan The tokenization in progress
     * @param WordScanner $words Reads the identifier bytes
     *
     * @return Lexeme The lexeme
     *
     * @throws LexicalException When no name follows
     */
    public function identifierOrKeyword(Scan $scan, WordScanner $words): Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $text = $words->consume($cursor);
        if ($text === '') {
            throw LexicalException::unexpectedCharacter($cursor->source, $start);
        }
        $scan->next = $cursor->peek() === '.' ? LexerState::IdentifierSeparator : LexerState::Start;
        $keyword = $scan->keywords->lookup($text, false, $scan->mode);

        return $keyword === null ? $words->identifierLexeme($scan, $start, $text) : $scan->lexeme($keyword, $start);
    }
}
