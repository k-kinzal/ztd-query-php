<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database\PostgreSql;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Validates the storage parameters a PostgreSQL tablespace defines.
 * @visibility SqlSemantics
 */
final class TablespaceInvariant
{
    /**
     * The planner cost and I/O concurrency parameters a tablespace can override.
     */
    public const PARAMETERS = ['seq_page_cost', 'random_page_cost', 'effective_io_concurrency', 'maintenance_io_concurrency'];

    /**
     * Requires known unqualified parameters, each given once with a number or text value in its domain: a cost is a
     * finite non-negative real, a concurrency an integer from 0 to 1000.
     * @param list<Parameter> $parameters Assigned parameters in request order
     * @throws InvalidStructure
     */
    public static function parameters(array $parameters): void
    {
        Collections::objects($parameters, Parameter::class);
        $names = [];
        foreach ($parameters as $parameter) {
            $value = $parameter->value;
            $name = $parameter->name->parts[0];
            $plain = $value instanceof Literal && preg_match("/^'((?:[^']|'')*)'$/sD", $value->text, $quoted) === 1 ? str_replace("''", "'", $quoted[1]) : null;
            $written = $value instanceof Literal && $value->literalKind === LiteralKind::Number ? $value->text : $plain;
            if (count($parameter->name->parts) !== 1 || !in_array($name, self::PARAMETERS, true) || in_array($name, $names, true) || !$value instanceof Literal
                || !in_array($value->literalKind, [LiteralKind::Number, LiteralKind::Text], true) || ($written !== null && !self::accepts($name, $written))) {
                throw new InvalidStructure('A tablespace parameter must be a known cost or concurrency setting, given once, with a value in its domain.');
            }
            $names[] = $name;
        }
    }

    /**
     * Whether PostgreSQL reads the written value of a parameter within its bounds: a cost as a real from 0, a
     * concurrency as an integer from 0 to 1000.
     */
    public static function accepts(string $name, string $value): bool
    {
        $integer = in_array($name, ['effective_io_concurrency', 'maintenance_io_concurrency'], true);
        $number = $integer ? self::integer($value) : self::real($value);
        return $number !== null && $number >= 0 && $number <= ($integer ? 1000 : PHP_FLOAT_MAX);
    }

    /**
     * Reads a real as C's strtod does, surrounded by optional whitespace; NaN, overflow and underflow are not values.
     */
    public static function real(string $value): ?float
    {
        if (preg_match('/^\s*([+-]?)(?:(inf(?:inity)?)|0x([0-9a-f]*)(?:\.([0-9a-f]*))?(?:p([+-]?\d+))?|((?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?))\s*$/Di', $value, $parts, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }
        $sign = $parts[1] === '-' ? -1.0 : 1.0;
        if (($parts[2] ?? null) !== null) {
            return $sign * INF;
        }
        $decimal = $parts[6] ?? '';
        if ($decimal === '') {
            $digits = ($parts[3] ?? '') . ($parts[4] ?? '');
            if ($digits === '') {
                return null;
            }
            $mantissa = 0.0;
            foreach (str_split(strtolower($digits)) as $digit) {
                $mantissa = $mantissa * 16 + (float) hexdec($digit);
            }
            $number = $mantissa * 2 ** ((int) ($parts[5] ?? '0') - 4 * strlen($parts[4] ?? ''));
        } else {
            $number = (float) $decimal;
        }
        $zero = preg_match('/^[0.]*(?:[eEpP]|$)/D', $decimal === '' ? ($parts[3] ?? '') . ($parts[4] ?? '') : $decimal) === 1;
        return is_infinite($number) || ($number === 0.0 && !$zero) ? null : $sign * $number;
    }

    /**
     * Reads an integer as C's strtol with base 0 does, falling back to a rounded real before a point or exponent,
     * surrounded by optional whitespace.
     */
    public static function integer(string $value): ?float
    {
        if (preg_match('/^\s*([+-]?)(0x[0-9a-f]+|0[0-7]*|[1-9][0-9]*)/i', $value, $parts) !== 1) {
            return null;
        }
        $rest = substr($value, strlen($parts[0]));
        if ($rest !== '' && in_array($rest[0], ['.', 'e', 'E'], true)) {
            $real = self::real($value);
            return $real === null ? null : round($real, 0, PHP_ROUND_HALF_EVEN);
        }
        if (trim($rest, " \t\n\v\f\r") !== '') {
            return null;
        }
        $digits = strtolower($parts[2]);
        $number = (float) (str_starts_with($digits, '0x') ? hexdec(substr($digits, 2)) : (strlen($digits) > 1 && $digits[0] === '0' ? octdec($digits) : $digits));
        return $parts[1] === '-' ? -$number : $number;
    }

    /**
     * Requires one- or two-part parameter names for removal.
     * @param list<QualifiedName> $names Removed parameters in request order
     * @throws InvalidStructure
     */
    public static function names(array $names): void
    {
        Collections::objects(Collections::nonEmpty($names), QualifiedName::class);
        foreach ($names as $name) {
            if (count($name->parts) > 2) {
                throw new InvalidStructure('A tablespace parameter name has at most a namespace and a name.');
            }
        }
    }
}
