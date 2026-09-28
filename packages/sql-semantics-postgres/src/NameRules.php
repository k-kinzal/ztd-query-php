<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Lexer\Token;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Core\Policy\NameRules as Contract;

/**
 * PostgreSql NameRules implementation.
 *
 * @visibility SqlSemantics
 */
final class NameRules implements Contract
{
    /**
     * Decodes identifier quoting and applies PostgreSql case folding.
     */
    public function name(Token $token): string
    {
        return $this->decode($token->text);
    }

    /**
     * Decodes the spelling of a name as written, quoted or bare, into the name the server stores.
     *
     * A bare name is folded to lower case; a Unicode name, U&"...", is decoded
     * with its escape character, a backslash unless UESCAPE names another,
     * and keeps its case. A name of 64 bytes or more is
     * truncated to the 63 bytes that fit in the server's NAMEDATALEN without
     * splitting a character, as the scanner's truncate_identifier() does, so
     * two names that differ only after their 63rd byte are the same name.
     *
     * @throws AnalysisException When a Unicode name has an invalid escape, which the server rejects
     */
    public function decode(string $text): string
    {
        $quote = substr($text, 0, 1);
        if (preg_match('/\A[uU]&"((?:[^"]|"")*)"(?:[\s\S]*\'([\s\S])\')?\z/', $text, $unicode) === 1) {
            try {
                $name = (new Literal\Escapes())->unicode(str_replace('""', '"', $unicode[1]), $unicode[2] ?? '\\');
            } catch (DecodingException $error) {
                throw new AnalysisException('Invalid Unicode escape in the identifier ' . $text . ': ' . $error->getMessage(), 0, $error);
            }
        } elseif (in_array($quote, ['"', '`', '['], true)) {
            $close = $quote === '[' ? ']' : $quote;
            $name = str_replace($close . $close, $close, substr($text, 1, -1));
        } else {
            $name = strtolower($text);
        }
        if (strlen($name) < 64) {
            return $name;
        }
        $length = 63;
        while ($length > 0 && (ord($name[$length]) & 0xC0) === 0x80) {
            $length--;
        }

        return substr($name, 0, $length);
    }

    /**
     * Compares column or correlation names using this dialect.
     */
    public function equal(string $left, string $right): bool
    {
        return $left === $right;
    }

    /**
     * Compares relation names under the default table name case policy.
     */
    public function relationEqual(string $left, string $right): bool
    {
        return $left === $right;
    }

    /**
     * Canonical comparison key for a column or correlation identifier.
     */
    public function key(string $name): string
    {
        return $name;
    }
}
