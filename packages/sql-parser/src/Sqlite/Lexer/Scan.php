<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Lexer;

use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;

/**
 * The state one SQLite tokenization carries from token to token.
 *
 * @visibility root
 */
final class Scan
{
    /**
     * Lexemes read so far, in text order.
     *
     * @var list<Lexeme>
     */
    public array $lexemes = [];

    /**
     * @param Cursor $cursor Reads the SQL text
     * @param KeywordTable $keywords Keywords of the release
     * @param array<string, true> $fallbacks Keywords the grammar lets fall back to identifiers
     */
    public function __construct(
        public readonly Cursor $cursor,
        public readonly KeywordTable $keywords,
        public readonly array $fallbacks = [],
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
     * Answers the lexeme read last, if any.
     *
     * @return Lexeme|null The last lexeme
     */
    public function last(): ?Lexeme
    {
        return $this->lexemes[count($this->lexemes) - 1] ?? null;
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
