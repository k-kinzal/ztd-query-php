<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Syntax\QuotedText;
use SqlParser\Lexer\Token;

/**
 * Decodes a MySQL string literal token into the text it denotes.
 *
 * @visibility root
 */
final class StringLiteral
{
    /**
     * Reads a TEXT_STRING or NCHAR_STRING token, dropping the national prefix and quotes.
     */
    public function decode(Token $token): string
    {
        $text = $token->is('NCHAR_STRING') ? substr($token->text, 1) : $token->text;
        $quote = substr($text, 0, 1);
        if (strlen($text) < 2 || ($quote !== "'" && $quote !== '"')) {
            return $text;
        }

        return $this->unescape(substr($text, 1, -1), $quote);
    }

    /**
     * Answers the bytes a hexadecimal or binary literal denotes.
     */
    public function bytes(Token $token): string
    {
        $text = $token->text;
        $digits = str_starts_with(strtolower($text), '0x') || str_starts_with(strtolower($text), '0b')
            ? substr($text, 2)
            : (new QuotedText())->unquote(substr($text, 1));
        if ($token->is('HEX_NUM')) {
            return (string) hex2bin(strlen($digits) % 2 === 0 ? $digits : '0' . $digits);
        }

        $bytes = '';
        $padded = str_pad($digits, (int) ceil(strlen($digits) / 8) * 8, '0', STR_PAD_LEFT);
        foreach (str_split($padded === '' ? '00000000' : $padded, 8) as $bits) {
            $bytes .= chr((int) bindec($bits));
        }

        return $bytes;
    }

    /**
     * Resolves backslash escapes and doubled quotes the way the MySQL lexer does.
     */
    public function unescape(string $inner, string $quote): string
    {
        $result = '';
        $length = strlen($inner);
        for ($index = 0; $index < $length; $index++) {
            $character = $inner[$index];
            if ($character === '\\' && $index + 1 < $length) {
                $escaped = $inner[++$index];
                $result .= match ($escaped) {
                    '0' => "\0",
                    'b' => "\x08",
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'Z' => "\x1a",
                    '%', '_' => '\\' . $escaped,
                    default => $escaped,
                };
                continue;
            }
            if ($character === $quote && ($inner[$index + 1] ?? '') === $quote) {
                $index++;
            }
            $result .= $character;
        }

        return $result;
    }
}
