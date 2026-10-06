<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules;

use SqlSemantics\Platform\PostgreSql\Rules\Lexical\UnicodeEscapes;

/**
 * Decodes the spelling of a PostgreSQL identifier token into the name the server uses.
 *
 * Rule: PG-IDENT-001. Scope: the `IDENT` terminal (plain, `"quoted"` and
 * `U&"..."` with an optional `UESCAPE` clause, which the lexer delivers as one
 * token) and every keyword terminal used as a name. An unquoted identifier and
 * a keyword are folded to lower case, ASCII letters only, as the server does
 * for a multibyte server encoding. A quoted identifier is taken literally with
 * each doubled quote read as one quote. A Unicode identifier has its escapes
 * replaced after the quotes are undoubled. Every name is then cut to 63 bytes
 * on a character boundary (NAMEDATALEN - 1), because the server stores no
 * longer identifier. Invalid Unicode escapes are lexical errors of the server
 * and are reported as SQL outside the grammar.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Identifiers
{
    /**
     * The longest name the server keeps, in bytes.
     */
    public const LIMIT = 63;

    /**
     * Decodes the text of an identifier token.
     */
    public function decode(string $text): string
    {
        if (str_starts_with($text, '"')) {
            return $this->clip(str_replace('""', '"', substr($text, 1, -1)));
        }
        if (preg_match('/\A[uU]&"((?:[^"]|"")*)"(.*)\z/s', $text, $match) === 1) {
            $escape = $match[2] === '' ? '\\' : substr($match[2], -2, 1);

            return $this->clip((new UnicodeEscapes())->decode(str_replace('""', '"', $match[1]), $escape));
        }

        return $this->clip($this->fold($text));
    }

    /**
     * Folds the ASCII letters of an unquoted word to lower case.
     */
    public function fold(string $word): string
    {
        return strtr($word, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * Cuts a name to the stored length without splitting a UTF-8 character.
     */
    public function clip(string $name): string
    {
        if (strlen($name) <= self::LIMIT) {
            return $name;
        }
        $length = self::LIMIT;
        while ($length > 0 && (ord($name[$length]) & 0xC0) === 0x80) {
            $length--;
        }

        return substr($name, 0, $length);
    }
}
