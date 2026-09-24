<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks and decodes the quoted MySQL string literals that replication and server commands accept.
 * @visibility SqlSemantics
 */
final class ReplicationText
{
    /**
     * Requires a plain quoted MySQL string, optionally on a single line, and returns its decoded bytes.
     * @throws InvalidStructure
     */
    public static function check(Literal $literal, string $operand, bool $singleLine = false): string
    {
        $quote = $literal->text[0] ?? '';
        if ($literal->type->dialect !== Dialect::MySql || $literal->literalKind !== LiteralKind::Text || !in_array($quote, ["'", '"'], true)) {
            throw new InvalidStructure($operand . ' requires a quoted MySQL string literal.');
        }
        $text = self::decode($literal->text);
        if ($singleLine && str_contains($text, "\n")) {
            throw new InvalidStructure($operand . ' cannot contain a line feed.');
        }
        return $text;
    }

    /**
     * Decodes MySQL string quoting and backslash escapes without evaluating anything else.
     */
    public static function decode(string $spelling): string
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
}
