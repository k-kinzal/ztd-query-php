<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

use RuntimeException;

/**
 * The SQL text could not be read at a position.
 *
 * Both a lexer that meets text it cannot tokenize and a parser that meets a
 * token it cannot accept report this way, so a caller can handle either.
 *
 * @visibility public
 *
 * @example Reporting where reading failed
 *     $e = \SqlParser\Lexer\LexicalException::unterminated('string', "SELECT\n'abc", 7);
 *     $e instanceof \SqlParser\Lexer\SourceException // => true
 *     $e->position->line // => 2
 */
abstract class SourceException extends RuntimeException
{
    /**
     * @param string $message What went wrong
     * @param int $offset Byte offset where it went wrong
     * @param SourcePosition $position The same place as a line and column
     */
    public function __construct(
        string $message,
        public readonly int $offset,
        public readonly SourcePosition $position,
    ) {
        parent::__construct($message);
    }
}
