<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special\Xml;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Ordering;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The functions and user variables of an XPath expression of EXTRACTVALUE and UPDATEXML, computed as the server's SQL functions.
 *
 * sum() adds the number each node's text starts with, quietly; last() is the size of the whole
 * step; number() of nothing is 0; floor(), ceiling() and round() answer doubles without decimals,
 * round() to the nearest even; string-length() counts characters; concat() joins its first two
 * arguments; contains() compares as the collation does; substring() counts as SUBSTRING does, its
 * numbers rounded to the nearest even integer. A NULL argument makes the result NULL. A user
 * variable reads as an integer, a double, or a string that converts quietly (verified on a live
 * 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html.
 *
 * @visibility MySqlMemory
 */
final class XPathFunctions
{
    /**
     * The fewest and the most arguments of each function; -1 is any number.
     */
    public const FUNCTIONS = [
        'count' => [1, 1], 'sum' => [1, 1], 'last' => [0, 0], 'position' => [0, 0], 'true' => [0, 0], 'false' => [0, 0],
        'not' => [1, 1], 'boolean' => [1, 1], 'number' => [0, 1], 'string-length' => [0, 1], 'concat' => [2, -1],
        'contains' => [2, 2], 'substring' => [2, 3], 'floor' => [1, 1], 'ceiling' => [1, 1], 'round' => [1, 1],
    ];

    /**
     * Computes a function over its argument values.
     *
     * @param list<XPathOperand> $values
     */
    public static function apply(string $name, array $values, XmlDocument $document, Frame $frame, Collation $collation, int $position, int $size): XPathOperand
    {
        return match ($name) {
            'count' => new XPathOperand(XPathOperand::INTEGER, count($values[0]->nodes)),
            'sum' => self::sum($values[0], $document),
            'last' => new XPathOperand(XPathOperand::INTEGER, $size),
            'position' => new XPathOperand(XPathOperand::INTEGER, $position),
            'true', 'false' => XPathOperand::truth($name === 'true'),
            'not', 'boolean' => self::logical($name, $values[0]->holds($document, $frame, $collation)),
            'number', 'floor', 'ceiling', 'round' => self::numeric($name, $values === [] ? 0.0 : $values[0]->real($document, $frame, $collation)),
            default => self::textual($name, $values, $document, $frame, $collation),
        };
    }

    /**
     * Computes sum(): the numbers the texts of the nodes start with, added.
     */
    public static function sum(XPathOperand $set, XmlDocument $document): XPathOperand
    {
        $sum = 0.0;
        foreach ($set->nodes as $node) {
            $sum += self::prefix(implode(' ', $document->texts([$node])));
        }

        return new XPathOperand(XPathOperand::DOUBLE, $sum);
    }

    /**
     * Computes not() or boolean() over the truth of their argument, NULL when it is unknown.
     */
    public static function logical(string $name, ?bool $holds): XPathOperand
    {
        return $holds === null ? new XPathOperand(XPathOperand::NULL) : XPathOperand::truth($name === 'not' ? !$holds : $holds);
    }

    /**
     * Computes number(), floor(), ceiling() or round() over the double of their argument, NULL when it is NULL.
     */
    public static function numeric(string $name, ?float $real): XPathOperand
    {
        if ($real === null) {
            return new XPathOperand(XPathOperand::NULL);
        }

        return match ($name) {
            'number' => new XPathOperand(XPathOperand::DOUBLE, $real),
            'floor' => new XPathOperand(XPathOperand::DOUBLE, floor($real), [], 0),
            'ceiling' => new XPathOperand(XPathOperand::DOUBLE, ceil($real), [], 0),
            default => new XPathOperand(XPathOperand::DOUBLE, round($real, 0, PHP_ROUND_HALF_EVEN), [], 0),
        };
    }

    /**
     * Computes string-length(), concat(), contains() or substring() over the texts of their arguments.
     *
     * @param list<XPathOperand> $values
     */
    public static function textual(string $name, array $values, XmlDocument $document, Frame $frame, Collation $collation): XPathOperand
    {
        $texts = array_map(static fn (XPathOperand $value): ?string => $value->text($document), $values);
        if (in_array(null, array_slice($texts, 0, $name === 'concat' ? 2 : count($texts)), true)) {
            return new XPathOperand(XPathOperand::NULL);
        }

        return match ($name) {
            'string-length' => new XPathOperand(XPathOperand::INTEGER, Encoding::length((string) $texts[0], $collation->charset)),
            'concat' => new XPathOperand(XPathOperand::STRING, $texts[0] . $texts[1]),
            'contains' => XPathOperand::truth(self::contains((string) $texts[0], (string) $texts[1], $collation)),
            default => self::substring($values, (string) $texts[0], $document, $frame, $collation),
        };
    }

    /**
     * Reads the number a text starts with, as the server sums the texts of nodes: quietly, 0 when there is none.
     */
    public static function prefix(string $text): float
    {
        return preg_match('/\A\s*[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/', $text, $match) === 1 ? (float) $match[0] : 0.0;
    }

    /**
     * Tells whether a text holds another, comparing as the collation does.
     */
    public static function contains(string $text, string $part, Collation $collation): bool
    {
        if ($part === '') {
            return true;
        }
        if ($collation->binaryOrder() || $collation->bytes()) {
            return str_contains($text, $part);
        }
        $ordering = Ordering::of($collation);
        $characters = Encoding::characters($text, $collation->charset);
        $length = count(Encoding::characters($part, $collation->charset));
        for ($i = 0; $i + $length <= count($characters); $i++) {
            if ($ordering->compare(implode('', array_slice($characters, $i, $length)), $part) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Computes substring(text, position[, length]) as SUBSTRING does, the numbers rounded to the nearest even integer.
     *
     * @param list<XPathOperand> $values
     */
    public static function substring(array $values, string $text, XmlDocument $document, Frame $frame, Collation $collation): XPathOperand
    {
        $start = $values[1]->real($document, $frame, $collation);
        $count = isset($values[2]) ? $values[2]->real($document, $frame, $collation) : null;
        if ($start === null || (isset($values[2]) && $count === null)) {
            return new XPathOperand(XPathOperand::NULL);
        }
        $start = (int) round($start, 0, PHP_ROUND_HALF_EVEN);
        $length = Encoding::length($text, $collation->charset);
        $count = $count === null ? $length : (int) round($count, 0, PHP_ROUND_HALF_EVEN);
        $from = $start > 0 ? $start - 1 : $length + $start;
        if ($start === 0 || $from < 0 || $count <= 0) {
            return new XPathOperand(XPathOperand::STRING, '');
        }

        return new XPathOperand(XPathOperand::STRING, Encoding::slice($text, $from, $count, $collation->charset));
    }

    /**
     * Answers the value of a user variable as XPath reads it.
     */
    public static function variable(Frame $frame, string $name): XPathOperand
    {
        [$value, $domain] = $frame->context->variables->user($name);
        if ($value === null) {
            return new XPathOperand(XPathOperand::NULL);
        }

        return match ($domain->kind) {
            Kind::Integer => new XPathOperand(XPathOperand::INTEGER, (int) $value),
            Kind::Decimal => new XPathOperand(XPathOperand::DOUBLE, (float) $value, [], 30),
            Kind::Double => new XPathOperand(XPathOperand::DOUBLE, (float) $value),
            Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => new XPathOperand(XPathOperand::STRING, (string) Convert::toText($value, $domain), [], XPathOperand::NOT_FIXED, true),
        };
    }
}
