<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads SQLite's operators and punctuation.
 *
 * @visibility root
 */
final class OperatorScanner
{
    /**
     * Terminal by spelling, longest spellings first.
     */
    public const OPERATORS = [
        '->>' => 'PTR', '->' => 'PTR', '||' => 'CONCAT', '==' => 'EQ', '<=' => 'LE', '<>' => 'NE', '!=' => 'NE',
        '>=' => 'GE', '<<' => 'LSHIFT', '>>' => 'RSHIFT', '(' => 'LP', ')' => 'RP', ';' => 'SEMI', ',' => 'COMMA',
        '.' => 'DOT', '=' => 'EQ', '<' => 'LT', '>' => 'GT', '+' => 'PLUS', '-' => 'MINUS', '*' => 'STAR',
        '/' => 'SLASH', '%' => 'REM', '&' => 'BITAND', '|' => 'BITOR', '~' => 'BITNOT',
    ];

    /**
     * Reads the operator at the cursor.
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
        foreach (self::OPERATORS as $spelling => $name) {
            if ($cursor->startsWith($spelling)) {
                $cursor->take(strlen($spelling));

                return $scan->lexeme($name, $start);
            }
        }

        throw LexicalException::unexpectedCharacter($cursor->source, $start);
    }
}
