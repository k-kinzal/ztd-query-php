<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the unsigned number literals of replication and server commands and reads the integer the parser takes from them.
 * @visibility SqlSemantics
 */
final class ReplicationNumber
{
    /**
     * Accepts decimal, fixed-point, floating-point and hexadecimal MySQL number literals.
     * @throws InvalidStructure
     */
    public static function check(Literal $literal, string $operand, bool $integral = false): void
    {
        $text = $literal->text;
        $hexadecimal = preg_match('/^(0x[0-9a-f]+|x\'[0-9a-f]*\')$/Di', $text) === 1;
        $number = $literal->literalKind === LiteralKind::Number && preg_match($integral ? '/^\d+$/D' : '/^(\d+(\.\d*)?|\.\d+)(e[+-]?\d+)?$/Di', $text) === 1;
        if ($literal->type->dialect !== Dialect::MySql || (!$hexadecimal && !$number)) {
            throw new InvalidStructure($operand . ' requires an unsigned MySQL number literal.');
        }
    }

    /**
     * Reads the integer prefix the MySQL parser converts the literal to, as a float so large values keep their order.
     */
    public static function magnitude(Literal $literal): float
    {
        $text = $literal->text;
        if (preg_match('/^(?:0x([0-9a-f]+)|x\'([0-9a-f]*)\')$/Di', $text, $hex) === 1) {
            $digits = $hex[1] . ($hex[2] ?? '');
            return $digits === '' ? 0.0 : (float) hexdec($digits);
        }
        return (float) ('0' . substr($text, 0, strspn($text, '0123456789')));
    }

    /**
     * Reads the numeric value of a fixed-point or floating-point literal, as for a heartbeat period.
     */
    public static function real(Literal $literal): float
    {
        $text = $literal->text;
        return str_starts_with(strtolower($text), 'x') || str_starts_with(strtolower($text), '0x') ? self::magnitude($literal) : (float) $text;
    }
}
