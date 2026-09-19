<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

/**
 * The state one MySQL tokenization carries from token to token.
 *
 * @visibility root
 */
final class Scan
{
    /**
     * State the next token is read in.
     */
    public LexerState $next = LexerState::Start;

    /**
     * Whether the text is inside a `/*!` comment whose body is read as SQL.
     */
    public bool $inVersionComment = false;

    /**
     * @param Cursor $cursor Reads the SQL text
     * @param KeywordTable $keywords Keywords of the release
     * @param SqlMode $mode Mode the text is read under
     * @param MySqlVersion $version Release the text is read for
     */
    public function __construct(
        public readonly Cursor $cursor,
        public readonly KeywordTable $keywords,
        public readonly SqlMode $mode,
        public readonly MySqlVersion $version,
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
     * Reports whether a byte can be part of an unquoted identifier.
     *
     * @param string $byte The byte, or an empty string at the end
     *
     * @return bool True for letters, digits, underscore, dollar and every byte of a multi-byte character
     */
    public static function isIdentifierByte(string $byte): bool
    {
        return $byte !== '' && (ctype_alnum($byte) || $byte === '_' || $byte === '$' || ord($byte) >= 0x80);
    }
}
