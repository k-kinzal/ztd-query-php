<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * The SQL text holds something no token of the dialect starts with.
 *
 * @visibility public
 *
 * @example Describing an unterminated string
 *     $e = \SqlParser\Lexer\LexicalException::unterminated('string', "SELECT 'abc", 7);
 *     $e->getMessage() // => 'Unterminated string at line 1, column 8'
 *     $e->offset // => 7
 */
final class LexicalException extends SourceException
{
    /**
     * Describes a construct that never closes.
     *
     * @param string $construct What was opened
     * @param string $source The SQL text
     * @param int $offset Byte offset where it opened
     *
     * @return self The exception
     */
    public static function unterminated(string $construct, string $source, int $offset): self
    {
        $position = SourcePosition::at($source, $offset);

        return new self("Unterminated {$construct} at line {$position->line}, column {$position->column}", $offset, $position);
    }

    /**
     * Describes a character no token starts with.
     *
     * @param string $source The SQL text
     * @param int $offset Byte offset of the character
     *
     * @return self The exception
     */
    public static function unexpectedCharacter(string $source, int $offset): self
    {
        $position = SourcePosition::at($source, $offset);
        $character = $source[$offset] ?? '';
        $shown = $character === '' ? 'end of input' : "'" . $character . "'";

        return new self("Unexpected {$shown} at line {$position->line}, column {$position->column}", $offset, $position);
    }

    /**
     * Describes a token the grammar of the selected release does not know.
     *
     * @param string $name Terminal name the lexer produced
     * @param string $source The SQL text
     * @param int $offset Byte offset of the token
     *
     * @return self The exception
     */
    public static function unknownTerminal(string $name, string $source, int $offset): self
    {
        $position = SourcePosition::at($source, $offset);

        return new self("Terminal {$name} is not part of the grammar at line {$position->line}, column {$position->column}", $offset, $position);
    }
}
