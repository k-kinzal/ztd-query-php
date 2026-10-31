<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\ParameterSyntax;
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
     * @param ParameterSyntax $parameters Which parameter markers are read
     * @param string|null $clientCharset Input character set for unquoted identifiers
     */
    public function __construct(
        public readonly Cursor $cursor,
        public readonly KeywordTable $keywords,
        public readonly SqlMode $mode,
        public readonly MySqlVersion $version,
        public readonly ParameterSyntax $parameters = ParameterSyntax::Native,
        public readonly ?string $clientCharset = null,
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
     * Latin-1 classes were observed through SQL on MySQL 5.7.44 and 8.4.7,
     * testing every high-bit byte independently. They are not UTF-8 byte classes.
     *
     * @param string|null $clientCharset Input character set
     * @return bool True for letters, digits, underscore, dollar and multibyte identifier bytes
     */
    public static function isIdentifierByte(string $byte, ?string $clientCharset = null): bool
    {
        if ($byte !== '' && ord($byte) >= 0x80 && strtolower($clientCharset ?? '') === 'latin1') {
            return preg_match('/^[\x83\x8A\x8C\x8E\x9A\x9C\x9E\x9F\xC0-\xD6\xD8-\xF6\xF8-\xFF]$/', $byte) === 1;
        }
        return $byte !== '' && ((\SqlParser\Lexer\Ascii::letter($byte) || \SqlParser\Lexer\Ascii::digit($byte)) || $byte === '_' || $byte === '$' || ord($byte) >= 0x80);
    }
}
