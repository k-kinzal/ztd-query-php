<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

use MySqlMemory\Value\Integer;

/**
 * Reads the text the server writes for a JSON value back into its tree ({@see JsonNode}).
 *
 * The text is one the server wrote ({@see Json::canonical()}), or the typed text of a document
 * ({@see JsonNode::typed()}), where a value whose type the text does not tell is written after a
 * backquote and the letter of its type ({@see JsonKind::mark()}). A number with a fraction or an
 * exponent is a double; any other number is an integer, unsigned when it lies beyond the signed
 * 64-bit range.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html.
 *
 * @visibility MySqlMemory
 */
final class JsonReader
{
    /**
     * Reads the value at a position of a text the server wrote, and moves the position past it.
     */
    public static function read(string $canonical, int &$at): JsonNode
    {
        $next = $canonical[$at];
        if ($next === '`') {
            $type = JsonKind::marked($canonical[$at + 1]);
            $at += 2;
            if ($canonical[$at] === '"') {
                return new JsonNode($type, self::characters($canonical, $at));
            }
            $length = strspn($canonical, '-0123456789.eE+', $at);
            $at += $length;

            return new JsonNode($type, substr($canonical, $at - $length, $length));
        }
        if ($next === '{' || $next === '[') {
            return self::container($canonical, $at);
        }
        if ($next === '"') {
            return new JsonNode(JsonKind::String, self::characters($canonical, $at));
        }
        foreach (['true' => new JsonNode(JsonKind::Boolean, true), 'false' => new JsonNode(JsonKind::Boolean, false), 'null' => new JsonNode(JsonKind::Null)] as $word => $node) {
            if (substr($canonical, $at, strlen($word)) === $word) {
                $at += strlen($word);

                return $node;
            }
        }

        return self::number($canonical, $at);
    }

    /**
     * Reads the array or the object at a position of a text the server wrote, and moves the position past it.
     */
    public static function container(string $canonical, int &$at): JsonNode
    {
        $object = $canonical[$at] === '{';
        $at++;
        $members = [];
        while ($canonical[$at] !== ($object ? '}' : ']')) {
            if ($object) {
                $name = self::characters($canonical, $at);
                $at += 2;
                $members[$name] = self::read($canonical, $at);
            } else {
                $members[] = self::read($canonical, $at);
            }
            if ($canonical[$at] === ',') {
                $at += 2;
            }
        }
        $at++;

        return $object ? new JsonNode(JsonKind::Object, $members) : new JsonNode(JsonKind::Array, array_values($members));
    }

    /**
     * Reads the number at a position of a text the server wrote, and moves the position past it.
     */
    public static function number(string $canonical, int &$at): JsonNode
    {
        $length = strspn($canonical, '-0123456789.eE+', $at);
        $number = substr($canonical, $at, $length);
        $at += $length;

        if (strpbrk($number, '.eE') !== false) {
            return new JsonNode(JsonKind::Double, (float) $number);
        }

        return new JsonNode($number[0] !== '-' && !Integer::signedRange($number) ? JsonKind::Unsigned : JsonKind::Integer, $number);
    }

    /**
     * Reads the characters of the string at a position of a text the server wrote, and moves the position past it.
     */
    public static function characters(string $canonical, int &$at): string
    {
        $characters = '';
        $at++;
        while (true) {
            $plain = strcspn($canonical, '"\\', $at);
            $characters .= substr($canonical, $at, $plain);
            $at += $plain;
            if ($canonical[$at] === '"') {
                $at++;

                return $characters;
            }
            $letter = $canonical[$at + 1];
            $characters .= match ($letter) {
                'b' => "\x08",
                'f' => "\x0c",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'u' => chr((int) hexdec(substr($canonical, $at + 2, 4))),
                default => $letter,
            };
            $at += $letter === 'u' ? 6 : 2;
        }
    }
}
