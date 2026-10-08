<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special\Xml;

use Closure;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Ordering;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The operators of an XPath expression of EXTRACTVALUE and UPDATEXML, computed as the server's SQL operations.
 *
 * `and` and `or` treat NULL as SQL does. `+`, `-`, `*`, `div` and `mod` compute over integers
 * when both sides are integers or truth values, a result beyond BIGINT being an error, and over
 * doubles that keep the most decimals of their sides otherwise; `div` divides into an integer,
 * and a division by zero is NULL with a warning. A comparison of strings follows the collation,
 * and a node set compares through each text directly in its nodes (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html.
 *
 * @visibility MySqlMemory
 */
final class XPathOperators
{
    /**
     * Combines two operands with AND or OR, as SQL does with NULL.
     *
     * @param array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string} $left
     * @param array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string} $right
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     */
    public static function logic(array $left, array $right, bool $and): array
    {
        $first = $left[1];
        $second = $right[1];

        return [XPathOperand::BOOLEAN, static function (XmlDocument $d, Frame $f, Collation $c, int $n, int $p, int $s) use ($first, $second, $and): XPathOperand {
            $a = $first($d, $f, $c, $n, $p, $s)->holds($d, $f, $c);
            $b = $second($d, $f, $c, $n, $p, $s)->holds($d, $f, $c);
            if ($and ? ($a === false || $b === false) : ($a === true || $b === true)) {
                return XPathOperand::truth(!$and);
            }

            return $a === null || $b === null ? new XPathOperand(XPathOperand::NULL) : XPathOperand::truth($and);
        }, '?'];
    }

    /**
     * Compares two values; a node set compares through each text directly in its nodes.
     */
    public static function compare(XPathOperand $left, XPathOperand $right, string $operator, XmlDocument $document, Frame $frame, Collation $collation): XPathOperand
    {
        if ($left->kind === XPathOperand::NULL || $right->kind === XPathOperand::NULL) {
            return new XPathOperand(XPathOperand::NULL);
        }
        if ($left->kind === XPathOperand::NODES || $right->kind === XPathOperand::NODES) {
            $set = $left->kind === XPathOperand::NODES ? $left : $right;
            foreach ($document->texts($set->nodes) as $text) {
                $value = new XPathOperand(XPathOperand::STRING, $text);
                $result = $left->kind === XPathOperand::NODES ? self::compare($value, $right, $operator, $document, $frame, $collation) : self::compare($left, $value, $operator, $document, $frame, $collation);
                if ($result->value === 1) {
                    return XPathOperand::truth(true);
                }
            }

            return XPathOperand::truth(false);
        }
        if ($left->kind === XPathOperand::STRING && $right->kind === XPathOperand::STRING) {
            $order = Ordering::of($collation)->compare((string) $left->value, (string) $right->value);
        } elseif (in_array($left->kind, [XPathOperand::INTEGER, XPathOperand::BOOLEAN], true) && in_array($right->kind, [XPathOperand::INTEGER, XPathOperand::BOOLEAN], true)) {
            $order = (int) $left->value <=> (int) $right->value;
        } else {
            $order = $left->real($document, $frame, $collation) <=> $right->real($document, $frame, $collation);
        }

        return XPathOperand::truth(match ($operator) {
            '=' => $order === 0,
            '!=' => $order !== 0,
            '<' => $order < 0,
            '<=' => $order <= 0,
            '>' => $order > 0,
            default => $order >= 0,
        });
    }

    /**
     * Answers the result of an integer operation, or null when it overflows into a double.
     */
    public static function exact(int|float $result): ?int
    {
        return is_int($result) ? $result : null;
    }

    /**
     * Negates an operand, as SQL does: an integer stays an integer, out of range at the smallest BIGINT.
     *
     * @param array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string} $operand
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     */
    public static function negate(array $operand): array
    {
        [, $closure, $printed] = $operand;

        return [XPathOperand::DOUBLE, static function (XmlDocument $d, Frame $f, Collation $c, int $n, int $p, int $s) use ($closure, $printed): XPathOperand {
            $value = $closure($d, $f, $c, $n, $p, $s);
            if ($value->kind === XPathOperand::INTEGER || $value->kind === XPathOperand::BOOLEAN) {
                if ($value->value === PHP_INT_MIN) {
                    throw DataError::DataOutOfRange->error('BIGINT', '-(' . $printed . ')');
                }

                return new XPathOperand(XPathOperand::INTEGER, -(int) $value->value);
            }
            if ($value->kind === XPathOperand::DOUBLE) {
                return new XPathOperand(XPathOperand::DOUBLE, -(float) $value->value, [], $value->decimals);
            }
            $real = $value->real($d, $f, $c);

            return $real === null ? new XPathOperand(XPathOperand::NULL) : new XPathOperand(XPathOperand::DOUBLE, -$real);
        }, '-' . $printed];
    }

    /**
     * Combines two operands with an arithmetic operator, as SQL does.
     *
     * @param array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string} $left
     * @param array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string} $right
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     */
    public static function arithmetic(array $left, array $right, string $operator): array
    {
        $first = $left[1];
        $second = $right[1];
        $printed = '(' . $left[2] . ' ' . match ($operator) {
            'mod' => '%',
            default => $operator,
        } . ' ' . $right[2] . ')';

        return [XPathOperand::DOUBLE, static function (XmlDocument $d, Frame $f, Collation $c, int $n, int $p, int $s) use ($first, $second, $operator, $printed): XPathOperand {
            $a = $first($d, $f, $c, $n, $p, $s);
            $b = $second($d, $f, $c, $n, $p, $s);
            if (in_array($a->kind, [XPathOperand::INTEGER, XPathOperand::BOOLEAN], true) && in_array($b->kind, [XPathOperand::INTEGER, XPathOperand::BOOLEAN], true)) {
                return self::integers((int) $a->value, (int) $b->value, $operator, $printed, $f);
            }

            return self::reals($a, $b, $operator, $d, $f, $c);
        }, $printed];
    }

    /**
     * Computes an arithmetic operator over two integers, as BIGINT arithmetic does.
     *
     * @param string $printed The operation as the server writes it in an out-of-range error
     *
     * @throws SqlError When the result is out of the range of BIGINT
     */
    public static function integers(int $x, int $y, string $operator, string $printed, Frame $frame): XPathOperand
    {
        if (($operator === 'div' || $operator === 'mod') && $y === 0) {
            $frame->context->warning(DataError::DivisionByZero);

            return new XPathOperand(XPathOperand::NULL);
        }
        $result = match ($operator) {
            '+' => self::exact($x + $y),
            '-' => self::exact($x - $y),
            '*' => self::exact($x * $y),
            'div' => $y === -1 ? self::exact(-$x) : intdiv($x, $y),
            default => $y === -1 ? 0 : $x % $y,
        };

        return $result === null ? throw DataError::DataOutOfRange->error('BIGINT', $printed) : new XPathOperand(XPathOperand::INTEGER, $result);
    }

    /**
     * Computes an arithmetic operator over two values read as doubles, keeping the most decimals of the two.
     */
    public static function reals(XPathOperand $a, XPathOperand $b, string $operator, XmlDocument $document, Frame $frame, Collation $collation): XPathOperand
    {
        $x = $a->real($document, $frame, $collation);
        $y = $b->real($document, $frame, $collation);
        if ($x === null || $y === null) {
            return new XPathOperand(XPathOperand::NULL);
        }
        $decimals = max($a->kind === XPathOperand::DOUBLE ? $a->decimals : ($a->numeric() ? 0 : XPathOperand::NOT_FIXED), $b->kind === XPathOperand::DOUBLE ? $b->decimals : ($b->numeric() ? 0 : XPathOperand::NOT_FIXED));
        if (($operator === 'div' || $operator === 'mod') && $y === 0.0) {
            $frame->context->warning(DataError::DivisionByZero);

            return new XPathOperand(XPathOperand::NULL);
        }

        return match ($operator) {
            '+' => new XPathOperand(XPathOperand::DOUBLE, $x + $y, [], $decimals),
            '-' => new XPathOperand(XPathOperand::DOUBLE, $x - $y, [], $decimals),
            '*' => new XPathOperand(XPathOperand::DOUBLE, $x * $y, [], $decimals),
            'div' => new XPathOperand(XPathOperand::INTEGER, (int) ($x / $y)),
            default => new XPathOperand(XPathOperand::DOUBLE, fmod($x, $y), [], $decimals),
        };
    }
}
