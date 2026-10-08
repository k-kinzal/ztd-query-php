<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;


/**
 * A JSON path: `$` and its legs, read as the server reads it, and the values it selects.
 *
 * Whitespace may come before `$`, between the legs and inside brackets. A member is `.` with a
 * name that is an ECMAScript identifier, a quoted string, or `*`; a cell is `[N]`, `[last]`,
 * `[last-N]`, `[*]`, or a range `[M to N]` with whitespace around `to`; `**` stands before a
 * member or a cell. A cell index is at most 4294967295, and a range whose first cell comes after
 * its last when both count from the same end is refused. A path that is not valid is refused
 * with the byte position the server names, which is where the reading stopped, past the
 * character it read when it expected a closing bracket.
 *
 * A path selects the values its legs select one after the other, each value once. With a
 * wildcard, a range or `**` it selects an array of them; without, the one value or nothing.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Searching and Modifying JSON
 * Values"); the positions were verified on a live 8.4 server.
 *
 * @visibility MySqlMemory
 */
final class JsonPath
{
    /**
     * The characters the server reads as whitespace in a path.
     */
    public const SPACE = " \t\n\x0b\x0c\r";

    /**
     * The message of a path that is not valid.
     */
    public const INVALID = 'Invalid JSON path expression.';

    /**
     * @param list<JsonLeg> $legs The legs in order
     */
    public function __construct(public readonly array $legs)
    {
    }

    /**
     * Reads a path.
     *
     * @example A member of a cell
     *     count(\MySqlMemory\Value\Json\JsonPath::parse('$[0].a')->legs) // => 2
     *
     * @throws JsonSyntax When the text is not a valid path, at the position the server names
     */
    public static function parse(string $text): self
    {
        $at = strspn($text, self::SPACE);
        if ($at >= strlen($text)) {
            throw new JsonSyntax(self::INVALID, $at);
        }
        if ($text[$at] !== '$') {
            throw new JsonSyntax(self::INVALID, $at + 1);
        }
        $at++;
        $legs = [];
        while (true) {
            $at += strspn($text, self::SPACE, $at);
            if ($at >= strlen($text)) {
                return new self($legs);
            }
            $legs[] = match ($text[$at]) {
                '.' => self::member($text, $at),
                '[' => self::cell($text, $at),
                '*' => self::descendants($text, $at),
                default => throw new JsonSyntax(self::INVALID, $at),
            };
        }
    }

    /**
     * Reads a member leg whose `.` is at the position, and moves the position past it.
     *
     * @throws JsonSyntax When the leg is not valid
     */
    public static function member(string $text, int &$at): JsonLeg
    {
        $at++;
        $at += strspn($text, self::SPACE, $at);
        $next = $text[$at] ?? '';
        if ($next === '*') {
            $at++;

            return new JsonLeg(JsonLegKind::AnyMember);
        }
        if ($next === '"') {
            $json = new Json($text);
            $json->at = $at;
            try {
                $name = $json->string();
            } catch (JsonSyntax $failure) {
                throw new JsonSyntax(self::INVALID, strlen($text), false, $failure);
            }
            $at = $json->at;

            return new JsonLeg(JsonLegKind::Member, $name);
        }
        $length = strcspn($text, self::SPACE . '.[*', $at);
        if ($length === 0) {
            throw new JsonSyntax(self::INVALID, $at);
        }
        $name = substr($text, $at, $length);
        $at += $length;
        if (preg_match('/^[\p{L}\p{Nl}$_][\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}$_\x{200C}\x{200D}]*$/u', $name) !== 1) {
            throw new JsonSyntax(self::INVALID, $at);
        }

        return new JsonLeg(JsonLegKind::Member, $name);
    }

    /**
     * Reads a cell, every cell or a range whose `[` is at the position, and moves the position past it.
     *
     * @throws JsonSyntax When the leg is not valid
     */
    public static function cell(string $text, int &$at): JsonLeg
    {
        $at++;
        $at += strspn($text, self::SPACE, $at);
        if (($text[$at] ?? '') === '*') {
            $at++;
            $at += strspn($text, self::SPACE, $at);
            self::close($text, $at);

            return new JsonLeg(JsonLegKind::AnyCell);
        }
        $from = self::index($text, $at);
        $space = strspn($text, self::SPACE, $at);
        $at += $space;
        if ($space > 0 && substr($text, $at, 2) === 'to' && strspn($text, self::SPACE, $at + 2) > 0) {
            $at += 2;
            $at += strspn($text, self::SPACE, $at);
            $to = self::index($text, $at);
            if ($from[0] === $to[0] && ($from[0] ? $from[1] < $to[1] : $from[1] > $to[1])) {
                throw new JsonSyntax(self::INVALID, $at);
            }
            $at += strspn($text, self::SPACE, $at);
            self::close($text, $at);

            return new JsonLeg(JsonLegKind::Range, '', $from, $to);
        }
        self::close($text, $at);

        return new JsonLeg(JsonLegKind::Cell, '', $from);
    }

    /**
     * Reads a cell index at the position: a number, `last` or `last-N`, and moves the position past it.
     *
     * @return array{bool, int} Whether the index counts from the last cell, and its distance
     *
     * @throws JsonSyntax When no index is at the position, or it is too big
     */
    public static function index(string $text, int &$at): array
    {
        $last = substr($text, $at, 4) === 'last';
        if ($last) {
            $at += 4;
            $after = $at + strspn($text, self::SPACE, $at);
            if (($text[$after] ?? '') !== '-') {
                return [true, 0];
            }
            $at = $after + 1;
            $at += strspn($text, self::SPACE, $at);
        }
        $digits = strspn($text, '0123456789', $at);
        if ($digits === 0) {
            throw new JsonSyntax(self::INVALID, $at);
        }
        $number = ltrim(substr($text, $at, $digits), '0');
        if (strlen($number) > 10 || (strlen($number) === 10 && strcmp($number, '4294967295') > 0)) {
            throw new JsonSyntax(self::INVALID, $at);
        }
        $at += $digits;

        return [$last, (int) $number];
    }

    /**
     * Reads the `]` that closes a cell at the position, and moves the position past it.
     *
     * @throws JsonSyntax When the text ends or another character is at the position
     */
    public static function close(string $text, int &$at): void
    {
        if ($at >= strlen($text)) {
            throw new JsonSyntax(self::INVALID, $at);
        }
        if ($text[$at] !== ']') {
            throw new JsonSyntax(self::INVALID, $at + 1);
        }
        $at++;
    }

    /**
     * Reads `**` at the position, which a member or a cell must follow, and moves the position past it.
     *
     * @throws JsonSyntax When the second `*` or the leg after it is missing
     */
    public static function descendants(string $text, int &$at): JsonLeg
    {
        if (($text[$at + 1] ?? '') !== '*') {
            throw new JsonSyntax(self::INVALID, $at + 1);
        }
        $at += 2;
        $at += strspn($text, self::SPACE, $at);
        if (!in_array($text[$at] ?? '', ['.', '['], true)) {
            throw new JsonSyntax(self::INVALID, $at);
        }

        return new JsonLeg(JsonLegKind::Descendants);
    }

    /**
     * Tells whether the path can select more than one value.
     *
     * @example A wildcard
     *     \MySqlMemory\Value\Json\JsonPath::parse('$[*]')->wild() // => true
     */
    public function wild(): bool
    {
        foreach ($this->legs as $leg) {
            if ($leg->kind->wild()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the values the path selects from a value, each once, in the order its legs select them.
     *
     * @return list<JsonNode>
     */
    public function select(JsonNode $root): array
    {
        $current = [$root];
        foreach ($this->legs as $leg) {
            $next = [];
            foreach ($current as $node) {
                foreach ($leg->apply($node) as $found) {
                    $next[spl_object_id($found)] ??= $found;
                }
            }
            $current = array_values($next);
        }

        return $current;
    }

    /**
     * Answers what JSON_EXTRACT() answers for the path: the value it selects, an array of the values a wild path selects, or null when it selects none.
     *
     * @example A range
     *     \MySqlMemory\Value\Json\JsonPath::parse('$[0 to 1]')->extract(\MySqlMemory\Value\Json\JsonNode::parse('[1, 2, 3]'))?->text() // => '[1, 2]'
     */
    public function extract(JsonNode $root): ?JsonNode
    {
        $found = $this->select($root);
        if ($found === []) {
            return null;
        }

        return $this->wild() ? new JsonNode(JsonKind::Array, $found) : $found[0];
    }
}
