<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

/**
 * Searches JSON values as JSON_CONTAINS, JSON_OVERLAPS and JSON_SEARCH do.
 *
 * A candidate is contained in a target when both are scalars that are equal; when the target is
 * an array, and every element of a candidate array, or a candidate that is no array, is contained
 * in some element of the target; or when both are objects and each member of the candidate is
 * contained in the member of the target with its name. Two values overlap when an element of one
 * equals an element of the other, a value that is no array taken as an array of itself; two
 * objects overlap when they have a member of the same name and an equal value. The path of a value is written as the server
 * writes it: `$`, then `[N]` for a cell and `.name` for a member, its name quoted when it is no
 * identifier (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html.
 *
 * @visibility MySqlMemory
 */
final class JsonSearch
{
    /**
     * Tells whether a candidate is contained in a target.
     *
     * @example A scalar in a nested array
     *     \MySqlMemory\Value\Json\JsonSearch::contains(\MySqlMemory\Value\Json\JsonNode::parse('[1, [3, 4]]'), \MySqlMemory\Value\Json\JsonNode::parse('[1, 3]')) // => true
     */
    public static function contains(JsonNode $target, JsonNode $candidate): bool
    {
        if ($target->type === JsonKind::Array) {
            $elements = $target->children();
            foreach ($candidate->type === JsonKind::Array ? $candidate->children() : [$candidate] as $wanted) {
                $found = false;
                foreach ($elements as $element) {
                    if (self::contains($element, $wanted)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return false;
                }
            }

            return true;
        }
        if ($target->type === JsonKind::Object) {
            if ($candidate->type !== JsonKind::Object || !is_array($candidate->value) || !is_array($target->value)) {
                return false;
            }
            foreach ($candidate->value as $name => $member) {
                if (!isset($target->value[$name]) || !self::contains($target->value[$name], $member)) {
                    return false;
                }
            }

            return true;
        }

        return $candidate->type !== JsonKind::Array && $candidate->type !== JsonKind::Object && $target->equals($candidate);
    }

    /**
     * Tells whether two values overlap.
     *
     * @example Arrays with a common element
     *     \MySqlMemory\Value\Json\JsonSearch::overlaps(\MySqlMemory\Value\Json\JsonNode::parse('[1, 2]'), \MySqlMemory\Value\Json\JsonNode::parse('[2, 3]')) // => true
     */
    public static function overlaps(JsonNode $left, JsonNode $right): bool
    {
        if ($left->type === JsonKind::Object && $right->type === JsonKind::Object && is_array($left->value) && is_array($right->value)) {
            foreach ($left->value as $name => $member) {
                if (isset($right->value[$name]) && $member->equals($right->value[$name])) {
                    return true;
                }
            }

            return false;
        }
        foreach ($left->type === JsonKind::Array ? $left->children() : [$left] as $mine) {
            foreach ($right->type === JsonKind::Array ? $right->children() : [$right] as $theirs) {
                if ($mine->equals($theirs)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Answers the path of each value a document holds, itself included, by the object id of the value.
     *
     * @return array<int, string>
     *
     * @example The path of a member of a cell
     *     array_values(\MySqlMemory\Value\Json\JsonSearch::paths(\MySqlMemory\Value\Json\JsonNode::parse('[{"a b": 1}]'))) // => ['$', '$[0]', '$[0]."a b"']
     */
    public static function paths(JsonNode $root, string $path = '$'): array
    {
        $paths = [spl_object_id($root) => $path];
        if (!is_array($root->value)) {
            return $paths;
        }
        foreach ($root->value as $key => $child) {
            $leg = $root->type === JsonKind::Array ? '[' . $key . ']' : '.' . self::name((string) $key);
            $paths += self::paths($child, $path . $leg);
        }

        return $paths;
    }

    /**
     * Writes the name of a member as a leg of a path writes it: as it is when it is an identifier, else quoted.
     *
     * @example A name with a space
     *     \MySqlMemory\Value\Json\JsonSearch::name('a b') // => '"a b"'
     */
    public static function name(string $name): string
    {
        return preg_match('/^[\p{L}\p{Nl}$_][\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}$_\x{200C}\x{200D}]*$/u', $name) === 1 ? $name : Json::quote($name);
    }
}
