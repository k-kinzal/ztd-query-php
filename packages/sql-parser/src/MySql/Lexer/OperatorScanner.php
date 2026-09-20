<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads MySQL's operators, punctuation and parameter markers.
 *
 * Comparison operators take the longest spelling the keyword table knows,
 * `&&` and `||` are the boolean operators, `:=` assigns, and `->` and `->>`
 * are the JSON operators of releases that have them. Every other punctuation
 * character is a token of its own.
 *
 * @visibility root
 */
final class OperatorScanner
{
    /**
     * Characters that are tokens of their own.
     */
    public const SINGLE = '()[],;+-*/%^~{}:=<>!&|.';

    /**
     * Reads the operator at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme The lexeme
     *
     * @throws LexicalException When the character starts no token at all
     */
    public function scan(Scan $scan): Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $character = $cursor->peek();
        if ($character === '?') {
            $cursor->take(1);
            if (Scan::isIdentifierByte($cursor->peek())) {
                throw LexicalException::unexpectedCharacter($cursor->source, $start);
            }

            return $scan->lexeme('PARAM_MARKER', $start);
        }
        foreach ([3, 2] as $length) {
            $candidate = substr($cursor->source, $start, $length);
            if (strlen($candidate) === $length && ($name = $this->multiCharacter($scan, $candidate)) !== null) {
                $cursor->take($length);

                return $scan->lexeme($name, $start);
            }
        }
        if ($character === '.') {
            return $this->separator($scan);
        }
        if (!str_contains(self::SINGLE, $character) || $character === '') {
            throw LexicalException::unexpectedCharacter($cursor->source, $start);
        }
        $cursor->take(1);

        return $scan->lexeme($scan->keywords->lookup($character, false, $scan->mode) ?? $character, $start);
    }

    /**
     * Names the terminal a two- or three-character operator stands for.
     *
     * @param Scan $scan The tokenization in progress
     * @param string $candidate The characters at the cursor
     *
     * @return string|null Terminal name, or null when they are not one operator
     */
    public function multiCharacter(Scan $scan, string $candidate): ?string
    {
        if ($candidate === ':=') {
            return 'SET_VAR';
        }
        if ($candidate === '->' || $candidate === '->>') {
            return $scan->version->hasJsonOperators() ? ($candidate === '->' ? 'JSON_SEPARATOR_SYM' : 'JSON_UNQUOTED_SEPARATOR_SYM') : null;
        }
        if (preg_match('/^[<>=!&|]+$/', $candidate) !== 1) {
            return null;
        }

        return $scan->keywords->lookup($candidate, false, $scan->mode);
    }

    /**
     * Reads the `.` that separates the parts of a qualified name.
     *
     * The word after it is read as an identifier whatever it spells, when
     * one follows at once.
     *
     * @param Scan $scan The tokenization in progress, positioned on the dot
     *
     * @return Lexeme The lexeme
     */
    public function separator(Scan $scan): Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $cursor->take(1);
        $scan->next = Scan::isIdentifierByte($cursor->peek()) ? LexerState::IdentifierStart : LexerState::Start;

        return $scan->lexeme('.', $start);
    }
}
