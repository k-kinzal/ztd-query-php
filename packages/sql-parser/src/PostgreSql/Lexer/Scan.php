<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql\Lexer;

use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\ParameterSyntax;

/**
 * The state one PostgreSQL tokenization carries from token to token.
 *
 * @visibility root
 */
final class Scan
{
    /**
     * @param Cursor $cursor Reads the SQL text
     * @param KeywordTable $keywords Keywords of the release
     * @param ParameterSyntax $parameters Which parameter markers are read
     */
    public function __construct(
        public readonly Cursor $cursor,
        public readonly KeywordTable $keywords,
        public readonly ParameterSyntax $parameters = ParameterSyntax::Native,
    ) {
    }

    /**
     * Makes the lexeme spanning from an offset to the cursor.
     *
     * @param string $name Terminal name
     * @param int $start Byte offset the lexeme began at
     *
     * @return Lexeme The lexeme
     */
    public function lexeme(string $name, int $start): Lexeme
    {
        return new Lexeme($name, substr($this->cursor->source, $start, $this->cursor->offset() - $start), $start);
    }

    /**
     * Reports whether a byte can begin an unquoted identifier.
     *
     * @param string $byte The byte, or an empty string at the end
     *
     * @return bool True for letters, underscore and every byte of a multi-byte character
     */
    public static function startsIdentifier(string $byte): bool
    {
        return $byte !== '' && (\SqlParser\Lexer\Ascii::letter($byte) || $byte === '_' || ord($byte) >= 0x80);
    }
}
