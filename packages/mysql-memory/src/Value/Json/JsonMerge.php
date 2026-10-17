<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

/**
 * The two ways the server merges JSON values: keeping every value, and applying a merge patch.
 *
 * - Preserving (JSON_MERGE_PRESERVE()): two objects make one object of the members of both,
 *   members of one name being merged in turn; any other two values make one array of the
 *   elements of both, a value that is no array counting as an array that holds only it.
 * - Patching (JSON_MERGE_PATCH(), RFC 7396): a patch that is no object is the result; an object
 *   patch is applied to the members of the target, or of an empty object when the target is no
 *   object: a member whose value is null is removed, and any other member is patched into the
 *   member of its name, so that null members of a new object are dropped too.
 *
 * Members are ordered as the server writes them ({@see JsonEdit::object()}), and values keep
 * their types (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-modification-functions.html,
 * https://tools.ietf.org/html/rfc7396.
 *
 * @visibility MySqlMemory
 */
final class JsonMerge
{
    /**
     * Merges two values, keeping every value.
     *
     * @example Members of one name
     *     \MySqlMemory\Value\Json\JsonMerge::preserve(\MySqlMemory\Value\Json\JsonNode::parse('{"a": 1}'), \MySqlMemory\Value\Json\JsonNode::parse('{"a": [2], "b": 3}'))->text() // => '{"a": [1, 2], "b": 3}'
     */
    public static function preserve(JsonNode $left, JsonNode $right): JsonNode
    {
        if ($left->type === JsonKind::Object && $right->type === JsonKind::Object && is_array($left->value) && is_array($right->value)) {
            $members = $left->value;
            foreach ($right->value as $name => $value) {
                $members[$name] = isset($members[$name]) ? self::preserve($members[$name], $value) : $value;
            }

            return JsonEdit::object($members);
        }
        $elements = [];
        foreach ([$left, $right] as $value) {
            array_push($elements, ...($value->type === JsonKind::Array ? $value->children() : [$value]));
        }

        return new JsonNode(JsonKind::Array, $elements);
    }

    /**
     * Applies a merge patch to a value.
     *
     * @example A null member removes the member
     *     \MySqlMemory\Value\Json\JsonMerge::patch(\MySqlMemory\Value\Json\JsonNode::parse('{"a": 1, "b": 2}'), \MySqlMemory\Value\Json\JsonNode::parse('{"a": null, "c": {"d": null}}'))->text() // => '{"b": 2, "c": {}}'
     */
    public static function patch(JsonNode $target, JsonNode $patch): JsonNode
    {
        if ($patch->type !== JsonKind::Object || !is_array($patch->value)) {
            return $patch;
        }
        $members = $target->type === JsonKind::Object && is_array($target->value) ? $target->value : [];
        foreach ($patch->value as $name => $value) {
            if ($value->type === JsonKind::Null) {
                unset($members[$name]);

                continue;
            }
            $members[$name] = self::patch($members[$name] ?? new JsonNode(JsonKind::Object, []), $value);
        }

        return JsonEdit::object($members);
    }
}
