<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads PostgreSQL's operators and punctuation.
 *
 * A user-defined operator is the longest run of operator characters that
 * does not start a comment. A run that ends in `+` or `-` gives those up
 * unless it also holds a character that only user-defined operators use, so
 * that `a<-1` reads as `a < -1`. A single character that stands for itself
 * and the few two-character comparisons have terminals of their own.
 *
 * @visibility root
 */
final class OperatorScanner
{
    /**
     * Characters that are tokens of their own.
     */
    public const SELF = ',()[].;:+-*/%^<>=';

    /**
     * Characters a user-defined operator may hold.
     */
    public const OPERATOR = '~!@#^&|`?+-*/%<>=';

    /**
     * Characters whose presence lets an operator keep a trailing `+` or `-`.
     */
    public const KEEPS_SIGN = '~!@#^&|`?%';

    /**
     * Terminals of the multi-character punctuation that is not an operator run.
     */
    public const FIXED = ['::' => 'TYPECAST', '..' => 'DOT_DOT', ':=' => 'COLON_EQUALS', '=>' => 'EQUALS_GREATER', '<=' => 'LESS_EQUALS', '>=' => 'GREATER_EQUALS', '<>' => 'NOT_EQUALS', '!=' => 'NOT_EQUALS'];

    /**
     * Reads the operator or punctuation at the cursor.
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
        $run = $this->operatorRun($cursor->source, $start);
        if ($run === null || strlen($run) === 1) {
            $pair = substr($cursor->source, $start, 2);
            if ($run === null && isset(self::FIXED[$pair])) {
                $cursor->take(2);

                return $scan->lexeme(self::FIXED[$pair], $start);
            }
            $character = $cursor->peek();
            if (str_contains(self::SELF, $character) && $character !== '') {
                $cursor->take(1);

                return $scan->lexeme($character, $start);
            }
            if ($run === null) {
                throw LexicalException::unexpectedCharacter($cursor->source, $start);
            }
        }
        $cursor->take(strlen($run));
        if (strlen($run) === 2 && isset(self::FIXED[$run])) {
            return $scan->lexeme(self::FIXED[$run], $start);
        }
        if (strlen($run) >= 64) {
            throw LexicalException::unexpectedCharacter($cursor->source, $start);
        }

        return $scan->lexeme('Op', $start);
    }

    /**
     * Answers the operator run at an offset, trimmed as the scanner trims it.
     *
     * @param string $source The SQL text
     * @param int $offset Where the run starts
     *
     * @return string|null The run, or null when no operator character is there
     */
    public function operatorRun(string $source, int $offset): ?string
    {
        $length = 0;
        while (isset($source[$offset + $length]) && str_contains(self::OPERATOR, $source[$offset + $length])) {
            $pair = substr($source, $offset + $length, 2);
            if ($length > 0 && ($pair === '/*' || $pair === '--')) {
                break;
            }
            $length++;
        }
        if ($length === 0) {
            return null;
        }
        $run = substr($source, $offset, $length);
        if (strpbrk($run, self::KEEPS_SIGN) === false) {
            $trimmed = rtrim($run, '+-');
            $run = $trimmed === '' ? $run[0] : $trimmed;
        }

        return $run;
    }
}
