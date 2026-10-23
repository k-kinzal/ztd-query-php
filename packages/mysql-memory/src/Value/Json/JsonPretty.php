<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

/**
 * Writes a JSON value as JSON_PRETTY() does.
 *
 * Each element of an array and each member of an object is written on a line of its own,
 * indented by two spaces more than the array or object that holds it, and separated from the next
 * by a comma; the closing bracket or brace is written on a line of its own at the indentation of
 * the opening one. A name is followed by `": "`. An empty array is written `[]` and an empty
 * object `{}`; a scalar is written as the server writes it anywhere else ({@see JsonNode::text()}),
 * so that a decimal keeps its digits and a temporal or an opaque value is a JSON string (verified
 * on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-utility-functions.html#function_json-pretty.
 *
 * @visibility MySqlMemory
 */
final class JsonPretty
{
    /**
     * Writes a value, its lines after the first indented by an indentation.
     *
     * @param string $indentation The indentation of the line the value starts on
     *
     * @example A nested array
     *     \MySqlMemory\Value\Json\JsonPretty::text(\MySqlMemory\Value\Json\JsonNode::parse('[1, []]')) // => "[\n  1,\n  []\n]"
     */
    public static function text(JsonNode $node, string $indentation = ''): string
    {
        $children = $node->children();
        if ($children === [] || !is_array($node->value)) {
            return $node->type === JsonKind::Object ? '{}' : ($node->type === JsonKind::Array ? '[]' : $node->text());
        }
        $inner = $indentation . '  ';
        $lines = [];
        $names = array_map('strval', array_keys($node->value));
        foreach ($children as $index => $child) {
            $lines[] = $inner . ($node->type === JsonKind::Object ? Json::quote($names[$index]) . ': ' : '') . self::text($child, $inner);
        }
        [$open, $close] = $node->type === JsonKind::Object ? ['{', '}'] : ['[', ']'];

        return $open . "\n" . implode(",\n", $lines) . "\n" . $indentation . $close;
    }
}
