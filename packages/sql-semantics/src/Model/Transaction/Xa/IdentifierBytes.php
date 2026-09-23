<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks a transaction identifier's literal category and encoded byte length.
 * @visibility SqlSemantics
 */
final class IdentifierBytes
{
    /**
     * Accepts only MySQL string, hexadecimal and bit literals of at most 64 bytes.
     * @throws InvalidStructure
     */
    public static function check(Literal $literal): void
    {
        if ($literal->type->dialect !== Dialect::MySql || self::length($literal) > 64) {
            throw new InvalidStructure('An XA identifier component requires a MySQL string literal of at most 64 bytes.');
        }
    }

    /**
     * Counts lexical bytes without constructing or evaluating the identifier value.
     * @throws InvalidStructure
     */
    public static function length(Literal $literal): int
    {
        $text = $literal->text;
        if (preg_match('/^(?:[xX]\'([0-9a-fA-F]*)\'|0[xX]([0-9a-fA-F]+))$/D', $text, $match) === 1) {
            return intdiv(strlen($match[2] ?? $match[1]) + 1, 2);
        }
        if (preg_match('/^(?:[bB]\'([01]*)\'|0[bB]([01]+))$/D', $text, $match) === 1) {
            return intdiv(strlen($match[2] ?? $match[1]) + 7, 8);
        }
        if ($literal->literalKind !== LiteralKind::Text || !in_array($text[0] ?? '', ["'", '"'], true)) {
            throw new InvalidStructure('An XA identifier component requires a string, hexadecimal, or bit literal.');
        }
        $quote = $text[0];
        $length = 0;
        for ($i = 1; $i < strlen($text) - 1; ++$i) {
            ++$length;
            if ($text[$i] === '\\' && isset($text[$i + 1])) {
                ++$i;
                $length += in_array($text[$i], ['%', '_'], true) ? 1 : 0;
            } elseif ($text[$i] === $quote && ($text[$i + 1] ?? '') === $quote) {
                ++$i;
            }
        }
        return $length;
    }
}
