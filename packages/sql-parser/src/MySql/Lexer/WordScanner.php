<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads MySQL's words: keywords, identifiers, introducers and dollar-quoted strings.
 *
 * A word followed at once by `.` and another word is an identifier whatever
 * it spells, which is how a schema may be named after a keyword. Otherwise
 * the word is looked up as a keyword, and as a function name too when a
 * parenthesis follows it at once.
 *
 * @visibility root
 */
final class WordScanner
{
    /**
     * Reads the word at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme|null The lexeme, or null when no word starts here
     *
     * @throws LexicalException When a dollar-quoted string never closes
     */
    public function scan(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $character = $cursor->peek();
        if ($character === '$' && $scan->version->hasDollarQuotedStrings()) {
            $dollar = $this->dollarQuoted($scan);
            if ($dollar !== null) {
                return $dollar;
            }
        }
        if (!Scan::isIdentifierByte($character) || ctype_digit($character)) {
            return null;
        }

        return $this->word($scan, $cursor->offset());
    }

    /**
     * Reads a word that may be a keyword.
     *
     * @param Scan $scan The tokenization in progress, positioned on the word
     * @param int $start Offset the word began at
     *
     * @return Lexeme The lexeme
     */
    public function word(Scan $scan, int $start): Lexeme
    {
        $cursor = $scan->cursor;
        $text = $this->consume($cursor);
        if ($cursor->peek() === '.' && Scan::isIdentifierByte($cursor->peek(1))) {
            $scan->next = LexerState::IdentifierSeparator;

            return $this->identifierLexeme($scan, $start, $text);
        }
        $end = $cursor->offset();
        if ($scan->mode->ignoreSpace) {
            $cursor->match('\s+');
        }
        $function = $cursor->peek() === '(';
        $cursor->seek($end);
        $keyword = $scan->keywords->lookup($text, $function, $scan->mode);
        if ($keyword !== null) {
            return $scan->lexeme($keyword, $start);
        }
        if ($text[0] === '_' && Charsets::has(substr($text, 1))) {
            return $scan->lexeme('UNDERSCORE_CHARSET', $start);
        }

        return $this->identifierLexeme($scan, $start, $text);
    }

    /**
     * Reads a word that is an identifier whatever it spells, as after `.`.
     *
     * @param Scan $scan The tokenization in progress, positioned on the word
     * @param int $start Offset the word began at
     *
     * @return Lexeme The lexeme
     */
    public function identifier(Scan $scan, int $start): Lexeme
    {
        $cursor = $scan->cursor;
        $text = $this->consume($cursor);
        if ($cursor->peek() === '.' && Scan::isIdentifierByte($cursor->peek(1))) {
            $scan->next = LexerState::IdentifierSeparator;
        }

        return $this->identifierLexeme($scan, $start, $text);
    }

    /**
     * Consumes the identifier bytes at the cursor.
     *
     * @param \SqlParser\Lexer\Cursor $cursor Reads the SQL text
     *
     * @return string The bytes consumed
     */
    public function consume(\SqlParser\Lexer\Cursor $cursor): string
    {
        $text = '';
        while (Scan::isIdentifierByte($cursor->peek())) {
            $text .= $cursor->take(1);
        }

        return $text;
    }

    /**
     * Makes the identifier lexeme MySQL would, quoted when it holds non-ASCII bytes.
     *
     * @param Scan $scan The tokenization in progress
     * @param int $start Offset the identifier began at
     * @param string $text The identifier as written
     *
     * @return Lexeme An IDENT or IDENT_QUOTED lexeme
     */
    public function identifierLexeme(Scan $scan, int $start, string $text): Lexeme
    {
        return $scan->lexeme(preg_match('/[\x80-\xFF]/', $text) === 1 ? 'IDENT_QUOTED' : 'IDENT', $start);
    }

    /**
     * Reads a dollar-quoted string, if one starts at the cursor.
     *
     * @param Scan $scan The tokenization in progress, positioned on the dollar sign
     *
     * @return Lexeme|null The lexeme, or null when the dollar sign starts an identifier
     *
     * @throws LexicalException When the string never closes
     */
    public function dollarQuoted(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $tag = $cursor->match('\$[A-Za-z0-9_\x80-\xFF]*\$');
        if ($tag === null) {
            return null;
        }
        $end = strpos($cursor->source, $tag, $cursor->offset());
        if ($end === false) {
            throw LexicalException::unterminated('dollar-quoted string', $cursor->source, $start);
        }
        $cursor->seek($end + strlen($tag));

        return $scan->lexeme('DOLLAR_QUOTED_STRING_SYM', $start);
    }
}
