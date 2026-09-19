<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads SQLite's bound parameters.
 *
 * A parameter is `?` with an optional number, or a name after `:`, `@`,
 * `$` or `#`. A name may hold `::` and end in a parenthesised suffix, the
 * spellings Tcl uses.
 *
 * @visibility root
 */
final class VariableScanner
{
    /**
     * Reads the parameter at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme|null The lexeme, or null when no parameter starts here
     *
     * @throws LexicalException When a sigil has no name after it or a suffix never closes
     */
    public function scan(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $character = $cursor->peek();
        if ($character === '?') {
            $cursor->match('\?[0-9]*');

            return $scan->lexeme('VARIABLE', $start);
        }
        if (!in_array($character, [':', '@', '$', '#'], true)) {
            return null;
        }
        $cursor->take(1);
        $length = 0;
        while (!$cursor->eof()) {
            $byte = $cursor->peek();
            if (Scan::isIdentifierByte($byte)) {
                $length++;
            } elseif ($byte === '(' && $length > 0) {
                if ($cursor->match('\([^\s)]*\)') === null) {
                    throw LexicalException::unterminated('parameter name', $cursor->source, $start);
                }
                break;
            } elseif ($byte !== ':' || $cursor->peek(1) !== ':') {
                break;
            } else {
                $cursor->take(1);
            }
            $cursor->take(1);
        }
        if ($length === 0) {
            throw LexicalException::unexpectedCharacter($cursor->source, $start);
        }

        return $scan->lexeme('VARIABLE', $start);
    }
}
