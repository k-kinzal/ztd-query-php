<?php

declare(strict_types=1);

namespace BisonParser\Scanner;

use BisonParser\Ast\Location;
use BisonParser\SyntaxException;

/**
 * Decodes the escape sequences of Bison's character and string literals, and writes them back.
 *
 * Bison decodes octal `\NNN`, hexadecimal `\xHH`, the C escapes `\a \b \f
 * \n \r \t \v`, the quoted `\" \' \? \\`, and universal characters `\uXXXX`
 * and `\UXXXXXXXX` that name a single byte. Anything else after a
 * backslash is an error.
 *
 * @visibility root
 */
final class Escapes
{
    /**
     * Decodes the body of a literal.
     *
     * @param string $body Text between the quotes
     * @param Location $location Where the literal begins, for the error
     *
     * @return string The bytes the body stands for
     *
     * @throws SyntaxException When an escape is not one Bison accepts
     */
    public function decode(string $body, Location $location): string
    {
        $decoded = '';
        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $byte = $body[$index];
            if ($byte !== '\\') {
                $decoded .= $byte;
                continue;
            }
            $rest = substr($body, $index + 1);
            [$bytes, $consumed] = $this->escape($rest, $location);
            $decoded .= $bytes;
            $index += $consumed;
        }

        return $decoded;
    }

    /**
     * Decodes the escape that starts right after a backslash.
     *
     * @param string $rest Text after the backslash
     * @param Location $location Where the literal begins, for the error
     *
     * @return array{string, int} The bytes and how many bytes of the text were used
     *
     * @throws SyntaxException When the escape is not one Bison accepts
     */
    public function escape(string $rest, Location $location): array
    {
        if (preg_match('/^[0-7]{1,3}/', $rest, $match) === 1) {
            return [$this->byte((int) octdec($match[0]), '\\' . $match[0], $location), strlen($match[0])];
        }
        if (preg_match('/^x([0-9A-Fa-f]+)/', $rest, $match) === 1) {
            return [$this->byte((int) hexdec(ltrim($match[1], '0') === '' ? '0' : substr(ltrim($match[1], '0'), 0, 8)), '\\' . $match[0], $location), strlen($match[0])];
        }
        if (preg_match('/^(u[0-9A-Fa-f]{4}|U[0-9A-Fa-f]{8})/', $rest, $match) === 1) {
            $code = hexdec(substr($match[0], 1));
            if ($code > 0xFF) {
                throw SyntaxException::invalid("invalid universal character name: \\{$match[0]}", $location);
            }

            return [chr((int) $code), strlen($match[0])];
        }
        $simple = ['a' => "\x07", 'b' => "\x08", 'f' => "\f", 'n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", '"' => '"', "'" => "'", '?' => '?', '\\' => '\\'];
        $first = $rest[0] ?? '';
        if (isset($simple[$first])) {
            return [$simple[$first], 1];
        }

        throw SyntaxException::invalid('invalid character after \\-escape: ' . ($first === '' ? 'end of literal' : $first), $location);
    }

    /**
     * Turns the number of an octal or hexadecimal escape into its byte, refusing what does not fit one.
     *
     * @param int $code The number
     * @param string $escape The escape as written, for the message
     * @param Location $location Where the literal is, for the message
     *
     * @return string The byte
     *
     * @throws SyntaxException When the number is above 255, which Bison rejects
     */
    public function byte(int $code, string $escape, Location $location): string
    {
        if ($code > 0xFF) {
            throw SyntaxException::invalid("invalid number after \\-escape: {$escape}", $location);
        }

        return chr($code);
    }

    /**
     * Writes bytes as the body of a literal, escaping what must be escaped.
     *
     * @param string $bytes The bytes
     * @param string $quote The quote the literal uses
     *
     * @return string Text to write between the quotes
     */
    public function encode(string $bytes, string $quote): string
    {
        $encoded = '';
        foreach (str_split($bytes) as $byte) {
            $encoded .= match (true) {
                $byte === '\\' => '\\\\',
                $byte === $quote => '\\' . $quote,
                $byte === "\n" => '\\n',
                $byte === "\t" => '\\t',
                $byte === "\r" => '\\r',
                ord($byte) < 0x20 || ord($byte) === 0x7F => sprintf('\\%03o', ord($byte)),
                default => $byte,
            };
        }

        return $encoded;
    }
}
