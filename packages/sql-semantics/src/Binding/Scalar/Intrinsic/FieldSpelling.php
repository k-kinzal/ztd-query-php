<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Statement\UnclassifiedSql;

/**
 * Decodes the lexical spelling of a static extraction unit, never a value operand.
 * @visibility SqlSemantics
 */
final class FieldSpelling
{
    /**
     * Reads identifiers, string continuations, dollar quoting, and Unicode escapes.
     * @throws UnclassifiedSql
     */
    public static function read(Token $token, Identifiers $identifiers): string
    {
        $text = $token->text;
        if ($token->name !== 'SCONST' && !str_starts_with(strtoupper($text), 'U&')) {
            return $identifiers->name($token);
        }
        if (preg_match('/^(\$(?:[^$]*)\$)(.*)\1$/s', $text, $dollar) === 1) {
            return $dollar[2];
        }
        $unicode = str_starts_with(strtoupper($text), 'U&');
        $escaped = str_starts_with(strtoupper($text), 'E');
        $escape = '\\';
        if ($unicode && preg_match("/\\s+UESCAPE\\s+'(.)'$/is", $text, $clause) === 1) {
            $escape = $clause[1];
            $text = substr($text, 0, -strlen($clause[0]));
        }
        $text = substr($text, $unicode ? 2 : ($escaped ? 1 : 0));
        $body = self::quoted($text, $escaped, $token->name === 'SCONST' ? "'" : '"');
        if ($unicode) {
            $quoted = preg_quote($escape, '/');
            return preg_replace_callback('/' . $quoted . '(\+[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|' . $quoted . ')/', static fn (array $match): string => $match[1] === $escape ? $escape : CharacterEscape::unicode((int) hexdec(ltrim($match[1], '+'))), $body) ?? $body;
        }
        return $escaped ? CharacterEscape::postgres($body) : $body;
    }

    /**
     * Joins the quoted pieces of a single lexer terminal, discarding inter-piece trivia.
     * @throws UnclassifiedSql
     */
    public static function quoted(string $text, bool $escaped, string $quote = "'"): string
    {
        $offset = 0;
        $result = '';
        $delimiter = preg_quote($quote, '/');
        $body = $escaped ? "(?:''|\\\\[\\s\\S]|[^'\\\\])*" : '(?:' . $delimiter . $delimiter . '|[^' . $delimiter . '])*';
        while ($offset < strlen($text)) {
            if (preg_match('/\\G' . $delimiter . '(' . $body . ')' . $delimiter . '/', $text, $part, 0, $offset) !== 1) {
                throw new UnclassifiedSql('Unclassified extraction-field literal spelling: ' . $text);
            }
            $result .= str_replace($quote . $quote, $quote, $part[1]);
            $offset += strlen($part[0]);
            if (preg_match('/\G(?:\s+|--[^\n\r]*)+/', $text, $trivia, 0, $offset) === 1) {
                $offset += strlen($trivia[0]);
            }
        }
        return $result;
    }
}
