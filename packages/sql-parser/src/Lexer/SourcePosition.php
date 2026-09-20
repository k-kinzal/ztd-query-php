<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * A line and column in SQL text, both counted from one.
 *
 * @visibility public
 *
 * @example Locating an offset
 *     $position = \SqlParser\Lexer\SourcePosition::at("SELECT 1\nFROM t", 12);
 *     [$position->line, $position->column] // => [2, 4]
 */
final class SourcePosition
{
    /**
     * @param int $line Line number counted from one
     * @param int $column Byte column counted from one
     */
    public function __construct(
        public readonly int $line,
        public readonly int $column,
    ) {
    }

    /**
     * Locates a byte offset in a text.
     *
     * @param string $source The text
     * @param int $offset Byte offset, clamped to the text's length
     *
     * @return self The position
     */
    public static function at(string $source, int $offset): self
    {
        $offset = max(0, min($offset, strlen($source)));
        $before = substr($source, 0, $offset);
        $lineStart = strrpos($before, "\n");

        return new self(
            substr_count($before, "\n") + 1,
            $offset - ($lineStart === false ? 0 : $lineStart + 1) + 1,
        );
    }
}
