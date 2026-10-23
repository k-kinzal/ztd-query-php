<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

/**
 * Changes of a JSON value at the place a path names, each answering a new value and leaving the given one as it is.
 *
 * A path names a place by its member and cell legs, read one after the other; a member is found
 * in an object only, and a cell in an array, where a value that is no array is taken as an array
 * that holds only it, so that `[0]` and `[last]` name the value itself. A path whose legs do not
 * all lead to a value names no place, and a change at it changes nothing.
 *
 * - Putting a value: the legs before the last name the place that receives it. A member of an
 *   object is replaced or added; a cell of an array is replaced, or added at the end past the
 *   last cell and at the start before the first, `last-N` counting back. A value that is no
 *   array is replaced by `[0]`, and becomes the first element of an array that the value is
 *   added to after it, or before it for a cell before the first. `$` replaces the whole value.
 *   Whether a value is replaced and whether one is added are chosen apart, for JSON_SET(),
 *   JSON_INSERT() and JSON_REPLACE().
 * - Removing: the value the path names is taken out of the array or object that holds it.
 * - Appending: the value the path names, when an array, gets the value as its last element, and
 *   another value becomes an array of it and the value.
 * - Inserting into an array: the value is put before the cell the last leg names, at the end
 *   past the last cell, and at the start for a cell before the first; a value that is no array
 *   is not changed.
 *
 * Members of an object are ordered as the server writes them, by the length of their names and
 * then by their bytes (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-modification-functions.html.
 *
 * @visibility MySqlMemory
 */
final class JsonEdit
{
    /**
     * Puts a value at the place a path names: replacing the value there, adding it where there is none, or both.
     *
     * @param bool $create Whether a value is added where there is none
     * @param bool $replace Whether a value that is there is replaced
     *
     * @example Setting a cell past the end of an array
     *     \MySqlMemory\Value\Json\JsonEdit::put(\MySqlMemory\Value\Json\JsonNode::parse('[1, 2]'), \MySqlMemory\Value\Json\JsonPath::parse('$[5]'), \MySqlMemory\Value\Json\JsonNode::parse('3'), true, true)->text() // => '[1, 2, 3]'
     */
    public static function put(JsonNode $root, JsonPath $path, JsonNode $value, bool $create, bool $replace): JsonNode
    {
        $legs = $path->legs;
        $last = array_pop($legs);
        if ($last === null) {
            return $replace ? $value : $root;
        }
        $steps = self::locate($root, $legs);
        if ($steps === null) {
            return $root;
        }
        $changed = self::place(self::at($root, $steps), $last, $value, $create, $replace);

        return $changed === null ? $root : self::rebuild($root, $steps, $changed);
    }

    /**
     * Answers what a value becomes when a value is put at its member or cell a leg names, or null when it does not change.
     *
     * @param bool $create Whether a value is added where there is none
     * @param bool $replace Whether a value that is there is replaced
     *
     * @example Adding a member
     *     \MySqlMemory\Value\Json\JsonEdit::place(\MySqlMemory\Value\Json\JsonNode::parse('{"b": 1}'), new \MySqlMemory\Value\Json\JsonLeg(\MySqlMemory\Value\Json\JsonLegKind::Member, 'a'), \MySqlMemory\Value\Json\JsonNode::parse('2'), true, false)?->text() // => '{"a": 2, "b": 1}'
     */
    public static function place(JsonNode $parent, JsonLeg $leg, JsonNode $value, bool $create, bool $replace): ?JsonNode
    {
        if ($leg->kind === JsonLegKind::Member) {
            if ($parent->type !== JsonKind::Object || !is_array($parent->value)) {
                return null;
            }
            $members = $parent->value;
            if (array_key_exists($leg->name, $members) ? !$replace : !$create) {
                return null;
            }
            $members[$leg->name] = $value;

            return self::object($members);
        }
        if ($leg->kind !== JsonLegKind::Cell) {
            return null;
        }
        $array = $parent->type === JsonKind::Array;
        $cells = $array ? $parent->children() : [$parent];
        $position = $leg->cell($leg->from, count($cells));
        if ($position >= 0 && $position < count($cells)) {
            if (!$replace) {
                return null;
            }
            if (!$array) {
                return $value;
            }
            $cells[$position] = $value;

            return new JsonNode(JsonKind::Array, $cells);
        }
        if (!$create) {
            return null;
        }

        return new JsonNode(JsonKind::Array, $position < 0 ? [$value, ...$cells] : [...$cells, $value]);
    }

    /**
     * Removes the value a path names from the array or object that holds it.
     *
     * @example A cell of a scalar names the scalar
     *     \MySqlMemory\Value\Json\JsonEdit::remove(\MySqlMemory\Value\Json\JsonNode::parse('{"a": 1, "b": 2}'), \MySqlMemory\Value\Json\JsonPath::parse('$.a[0]'))->text() // => '{"b": 2}'
     */
    public static function remove(JsonNode $root, JsonPath $path): JsonNode
    {
        $steps = self::locate($root, $path->legs);
        $step = $steps === null ? null : array_pop($steps);
        if ($steps === null || $step === null) {
            return $root;
        }
        $parent = self::at($root, $steps);
        $values = is_array($parent->value) ? $parent->value : [];
        unset($values[$step]);

        return self::rebuild($root, $steps, $parent->type === JsonKind::Array ? new JsonNode(JsonKind::Array, array_values($values)) : new JsonNode(JsonKind::Object, $values));
    }

    /**
     * Appends a value to the array a path names, making a value that is no array an array of it and the value.
     *
     * @example Wrapping a scalar
     *     \MySqlMemory\Value\Json\JsonEdit::append(\MySqlMemory\Value\Json\JsonNode::parse('{"a": 1}'), \MySqlMemory\Value\Json\JsonPath::parse('$.a'), \MySqlMemory\Value\Json\JsonNode::parse('2'))->text() // => '{"a": [1, 2]}'
     */
    public static function append(JsonNode $root, JsonPath $path, JsonNode $value): JsonNode
    {
        $steps = self::locate($root, $path->legs);
        if ($steps === null) {
            return $root;
        }
        $node = self::at($root, $steps);
        $cells = $node->type === JsonKind::Array ? $node->children() : [$node];

        return self::rebuild($root, $steps, new JsonNode(JsonKind::Array, [...$cells, $value]));
    }

    /**
     * Inserts a value into an array before the cell the last leg of a path names, at the end past the last cell and at the start before the first.
     *
     * A path whose last leg is no cell, or whose place is no array, changes nothing.
     *
     * @example Before the last cell
     *     \MySqlMemory\Value\Json\JsonEdit::insert(\MySqlMemory\Value\Json\JsonNode::parse('[1, 2]'), \MySqlMemory\Value\Json\JsonPath::parse('$[last]'), \MySqlMemory\Value\Json\JsonNode::parse('0'))->text() // => '[1, 0, 2]'
     */
    public static function insert(JsonNode $root, JsonPath $path, JsonNode $value): JsonNode
    {
        $legs = $path->legs;
        $last = array_pop($legs);
        $steps = self::locate($root, $legs);
        if ($last === null || $last->kind !== JsonLegKind::Cell || $steps === null) {
            return $root;
        }
        $node = self::at($root, $steps);
        if ($node->type !== JsonKind::Array) {
            return $root;
        }
        $cells = $node->children();
        $position = max(0, min(count($cells), $last->cell($last->from, count($cells))));
        array_splice($cells, $position, 0, [$value]);

        return self::rebuild($root, $steps, new JsonNode(JsonKind::Array, $cells));
    }

    /**
     * Answers the steps from a value to the place member and cell legs name: the name of each member and the position of each cell, or null when there is no such place.
     *
     * A cell of a value that is no array is the value itself and takes no step; a leg of another kind names no place.
     *
     * @param list<JsonLeg> $legs
     *
     * @return list<int|string>|null
     *
     * @example A member of a cell
     *     \MySqlMemory\Value\Json\JsonEdit::locate(\MySqlMemory\Value\Json\JsonNode::parse('[{"a": 1}]'), \MySqlMemory\Value\Json\JsonPath::parse('$[last].a[0]')->legs) // => [0, 'a']
     */
    public static function locate(JsonNode $root, array $legs): ?array
    {
        $steps = [];
        $node = $root;
        foreach ($legs as $leg) {
            if ($leg->kind === JsonLegKind::Member) {
                if ($node->type !== JsonKind::Object || !is_array($node->value) || !array_key_exists($leg->name, $node->value)) {
                    return null;
                }
                $steps[] = $leg->name;
                $node = $node->value[$leg->name];

                continue;
            }
            if ($leg->kind !== JsonLegKind::Cell) {
                return null;
            }
            $array = $node->type === JsonKind::Array;
            $cells = $array ? $node->children() : [$node];
            $position = $leg->cell($leg->from, count($cells));
            if (!isset($cells[$position])) {
                return null;
            }
            if ($array) {
                $steps[] = $position;
                $node = $cells[$position];
            }
        }

        return $steps;
    }

    /**
     * Answers the value at the end of steps from a value ({@see self::locate()}).
     *
     * @param list<int|string> $steps
     *
     * @example A cell of a member
     *     \MySqlMemory\Value\Json\JsonEdit::at(\MySqlMemory\Value\Json\JsonNode::parse('{"a": [1, 2]}'), ['a', 1])->text() // => '2'
     */
    public static function at(JsonNode $root, array $steps): JsonNode
    {
        $node = $root;
        foreach ($steps as $step) {
            $node = is_array($node->value) ? $node->value[$step] : $node;
        }

        return $node;
    }

    /**
     * Answers a value in which the value at the end of steps is replaced by another.
     *
     * @param list<int|string> $steps
     *
     * @example A cell of a member
     *     \MySqlMemory\Value\Json\JsonEdit::rebuild(\MySqlMemory\Value\Json\JsonNode::parse('{"a": [1, 2]}'), ['a', 1], \MySqlMemory\Value\Json\JsonNode::parse('true'))->text() // => '{"a": [1, true]}'
     */
    public static function rebuild(JsonNode $root, array $steps, JsonNode $replacement): JsonNode
    {
        $step = array_shift($steps);
        if ($step === null || !is_array($root->value)) {
            return $replacement;
        }
        $values = $root->value;
        $values[$step] = self::rebuild($values[$step], $steps, $replacement);

        return new JsonNode($root->type, $values);
    }

    /**
     * Makes an object of members, ordered as the server writes them: by the length of their names, then by their bytes.
     *
     * @param array<int|string, JsonNode> $members
     *
     * @example Members by the length of their names
     *     \MySqlMemory\Value\Json\JsonEdit::object(['bb' => \MySqlMemory\Value\Json\JsonNode::parse('1'), '10' => \MySqlMemory\Value\Json\JsonNode::parse('2'), 'c' => \MySqlMemory\Value\Json\JsonNode::parse('3')])->text() // => '{"c": 3, "10": 2, "bb": 1}'
     */
    public static function object(array $members): JsonNode
    {
        $names = array_map('strval', array_keys($members));
        usort($names, static fn (string $left, string $right): int => strlen($left) === strlen($right) ? strcmp($left, $right) : strlen($left) <=> strlen($right));
        $ordered = [];
        foreach ($names as $name) {
            $ordered[$name] = $members[$name];
        }

        return new JsonNode(JsonKind::Object, $ordered);
    }

    /**
     * Answers how deeply arrays and objects nest in a value: 0 for a scalar, 1 for an array or object that holds only scalars.
     *
     * @example A scalar in two arrays
     *     \MySqlMemory\Value\Json\JsonEdit::nesting(\MySqlMemory\Value\Json\JsonNode::parse('[1, [2]]')) // => 2
     */
    public static function nesting(JsonNode $node): int
    {
        if ($node->type !== JsonKind::Array && $node->type !== JsonKind::Object) {
            return 0;
        }
        $deepest = 0;
        foreach ($node->children() as $child) {
            $deepest = max($deepest, self::nesting($child));
        }

        return $deepest + 1;
    }
}
