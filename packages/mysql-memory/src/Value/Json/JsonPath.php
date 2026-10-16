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
        $path = \SqlSemantics\Platform\MySql\Statement\Call\Json\Path::read($text);
        if ($path instanceof \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid) {
            throw new JsonSyntax(self::INVALID, $path->position);
        }
        return self::from($path);
    }

    /**
     * Creates runtime selectors from the path Semantics already interpreted.
     */
    public static function from(\SqlSemantics\Platform\MySql\Statement\Call\Json\Path $path): self
    {
        return new self(array_map(JsonLeg::from(...), $path->legs));
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
