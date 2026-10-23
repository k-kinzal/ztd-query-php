<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Text\Json;

use JsonException;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg;

/**
 * Interprets JSON path operands, with the byte boundaries exposed by MySQL.
 *
 * It reads decoded operand values and never reads SQL source or a parser tree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PathSyntax
{
    /**
     * Whitespace accepted between path selection steps.
     */
    public const SPACE = " \t\n\x0b\x0c\r";

    /**
     * The condition shared by malformed path operands.
     */
    public const INVALID = 'Invalid JSON path expression.';

    /**
     * Reads a decoded path operand into selection steps.
     *
     * @throws Failure When the operand is not a path
     */
    public static function parse(string $text): Path
    {
        $at = strspn($text, self::SPACE);
        if ($at >= strlen($text)) {
            throw new Failure(self::INVALID, $at);
        }
        if ($text[$at] !== '$') {
            throw new Failure(self::INVALID, $at + 1);
        }
        $at++;
        $legs = [];
        while (true) {
            $at += strspn($text, self::SPACE, $at);
            if ($at >= strlen($text)) {
                return new Path($legs);
            }
            $legs[] = match ($text[$at]) {
                '.' => self::member($text, $at),
                '[' => self::cell($text, $at),
                '*' => self::descendants($text, $at),
                default => throw new Failure(self::INVALID, $at),
            };
        }
    }

    /**
     * Reads a member leg whose `.` is at the position, and moves the position past it.
     *
     * @throws Failure When the leg is not valid
     */
    public static function member(string $text, int &$at): Leg
    {
        $at++;
        $at += strspn($text, self::SPACE, $at);
        $next = $text[$at] ?? '';
        if ($next === '*') {
            $at++;

            return new Leg(Kind::AnyMember);
        }
        if ($next === '"') {
            $json = new Syntax($text);
            $json->at = $at;
            try {
                $json->string();
                $name = json_decode(substr($text, $at, $json->at - $at), true, flags: JSON_THROW_ON_ERROR);
            } catch (Failure|JsonException $failure) {
                throw new Failure(self::INVALID, strlen($text), previous: $failure);
            }
            assert(is_string($name));
            $at = $json->at;

            return new Leg(Kind::Member, $name);
        }
        $length = strcspn($text, self::SPACE . '.[*', $at);
        if ($length === 0) {
            throw new Failure(self::INVALID, $at);
        }
        $name = substr($text, $at, $length);
        $at += $length;
        if (preg_match('/^[\p{L}\p{Nl}$_][\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}$_\x{200C}\x{200D}]*$/u', $name) !== 1) {
            throw new Failure(self::INVALID, $at);
        }

        return new Leg(Kind::Member, $name);
    }

    /**
     * Reads a cell, every cell or a range whose `[` is at the position, and moves the position past it.
     *
     * @throws Failure When the leg is not valid
     */
    public static function cell(string $text, int &$at): Leg
    {
        $at++;
        $at += strspn($text, self::SPACE, $at);
        if (($text[$at] ?? '') === '*') {
            $at++;
            $at += strspn($text, self::SPACE, $at);
            self::close($text, $at);

            return new Leg(Kind::AnyCell);
        }
        $from = self::index($text, $at);
        $space = strspn($text, self::SPACE, $at);
        $at += $space;
        if ($space > 0 && substr($text, $at, 2) === 'to' && strspn($text, self::SPACE, $at + 2) > 0) {
            $at += 2;
            $at += strspn($text, self::SPACE, $at);
            $to = self::index($text, $at);
            if ($from->last === $to->last && ($from->last ? $from->offset < $to->offset : $from->offset > $to->offset)) {
                throw new Failure(self::INVALID, $at);
            }
            $at += strspn($text, self::SPACE, $at);
            self::close($text, $at);

            return new Leg(Kind::Range, from: $from, to: $to);
        }
        self::close($text, $at);

        return new Leg(Kind::Cell, from: $from);
    }

    /**
     * Reads a cell index at the position: a number, `last` or `last-N`, and moves the position past it.
     *
     * @return Index The end and distance
     *
     * @throws Failure When no index is at the position, or it is too big
     */
    public static function index(string $text, int &$at): Index
    {
        $last = substr($text, $at, 4) === 'last';
        if ($last) {
            $at += 4;
            $after = $at + strspn($text, self::SPACE, $at);
            if (($text[$after] ?? '') !== '-') {
                return new Index(true, 0);
            }
            $at = $after + 1;
            $at += strspn($text, self::SPACE, $at);
        }
        $digits = strspn($text, '0123456789', $at);
        if ($digits === 0) {
            throw new Failure(self::INVALID, $at);
        }
        $number = ltrim(substr($text, $at, $digits), '0');
        if (strlen($number) > 10 || (strlen($number) === 10 && strcmp($number, '4294967295') > 0)) {
            throw new Failure(self::INVALID, $at);
        }
        $at += $digits;

        return new Index($last, (int) $number);
    }

    /**
     * Reads the `]` that closes a cell at the position, and moves the position past it.
     *
     * @throws Failure When the text ends or another character is at the position
     */
    public static function close(string $text, int &$at): void
    {
        if ($at >= strlen($text)) {
            throw new Failure(self::INVALID, $at);
        }
        if ($text[$at] !== ']') {
            throw new Failure(self::INVALID, $at + 1);
        }
        $at++;
    }

    /**
     * Reads `**` at the position, which a member or a cell must follow, and moves the position past it.
     *
     * @throws Failure When the second `*` or the leg after it is missing
     */
    public static function descendants(string $text, int &$at): Leg
    {
        if (($text[$at + 1] ?? '') !== '*') {
            throw new Failure(self::INVALID, $at + 1);
        }
        $at += 2;
        $at += strspn($text, self::SPACE, $at);
        if (!in_array($text[$at] ?? '', ['.', '['], true)) {
            throw new Failure(self::INVALID, $at);
        }

        return new Leg(Kind::Descendants);
    }

}
