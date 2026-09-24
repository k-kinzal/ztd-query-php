<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Decodes the string, hexadecimal and bit literals that spell load separators to the bytes the server compares.
 * @visibility SqlSemantics
 */
final class SeparatorText
{
    /**
     * Returns the separator bytes; other literal kinds cannot spell a separator.
     * @throws InvalidStructure
     */
    public static function bytes(Literal $literal): string
    {
        $text = $literal->text;
        $prefix = strtolower(substr($text, 0, 2));
        if ($literal->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A load separator requires a MySQL literal.');
        }
        return match (true) {
            $literal->literalKind === LiteralKind::Text && in_array($text[0] ?? '', ["'", '"'], true) => self::text($text),
            $prefix === "x'" => (string) hex2bin(substr($text, 2, -1)),
            $prefix === '0x' => (string) hex2bin(str_pad(substr($text, 2), (strlen($text) - 1) & ~1, '0', STR_PAD_LEFT)),
            $prefix === "b'" => self::bits(substr($text, 2, -1)),
            $prefix === '0b' => self::bits(substr($text, 2)),
            default => throw new InvalidStructure('A load separator is a string, hexadecimal or bit literal.'),
        };
    }

    /**
     * Decodes MySQL string quoting and backslash escapes.
     */
    public static function text(string $spelling): string
    {
        $quote = $spelling[0] ?? '';
        $decoded = '';
        for ($i = 1; $i < strlen($spelling) - 1; ++$i) {
            $character = $spelling[$i];
            if ($character === '\\' && $i + 1 < strlen($spelling) - 1) {
                $character = $spelling[++$i];
                $decoded .= match ($character) {
                    '0' => "\0", 'n' => "\n", 'r' => "\r", 'b' => "\x08", 't' => "\t", 'Z' => "\x1a", '%', '_' => '\\' . $character, default => $character,
                };
                continue;
            }
            $decoded .= $character;
            if ($character === $quote && ($spelling[$i + 1] ?? '') === $quote) {
                ++$i;
            }
        }
        return $decoded;
    }

    /**
     * Packs binary digits into bytes, padding the most significant byte with zero bits.
     */
    public static function bits(string $digits): string
    {
        $bytes = '';
        foreach (str_split(str_pad($digits, (int) ceil(strlen($digits) / 8) * 8, '0', STR_PAD_LEFT), 8) as $byte) {
            $bytes .= $byte === '' ? '' : chr((int) bindec($byte));
        }
        return $bytes;
    }
}
