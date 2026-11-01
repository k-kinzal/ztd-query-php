<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

use MySqlMemory\Value\Decimal;

/**
 * A JSON Schema (draft 4) and the validation of documents against it, as JSON_SCHEMA_VALID and JSON_SCHEMA_VALIDATION_REPORT validate.
 *
 * A value is checked keyword by keyword in the order the server checks them, and the first
 * failure is reported with the keyword, the location of the schema that holds it and the location
 * of the value, each a JSON pointer from `#`: first `type`; for a number `minimum` (and
 * `exclusiveMinimum`), `maximum` (and `exclusiveMaximum`) and `multipleOf`, compared exactly; for
 * a string `minLength`, `maxLength` (in characters) and `pattern`; for an object each member in
 * order (refused by `additionalProperties: false`, else checked against its `properties` schema,
 * the `patternProperties` schemas its name matches, failing as `patternProperties`, or the
 * `additionalProperties` schema), then `required`, `minProperties`, `maxProperties` and
 * `dependencies`; for an array each element in order (against `items` or, past an `items` array,
 * `additionalItems`, then `uniqueItems`), then `minItems` and `maxItems`; then for any value
 * `enum`, `allOf`, `anyOf`, `oneOf` and `not`. A `$ref` to a pointer in the schema is followed; one
 * that finds nothing is ignored, and so is a keyword whose value has the wrong type, an unknown
 * keyword, `format` and a `pattern` that is no regular expression. A type that is not a JSON
 * Schema type matches nothing (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-validation-functions.html, https://json-schema.org/draft-04/json-schema-validation.
 *
 * @visibility MySqlMemory
 */
final class JsonSchema
{
    /**
     * @param JsonNode $root The schema: an object
     */
    public function __construct(public readonly JsonNode $root)
    {
    }

    /**
     * Tells whether the schema refers to a document outside itself, which the server does not support.
     *
     * @example A remote reference
     *     (new \MySqlMemory\Value\Json\JsonSchema(\MySqlMemory\Value\Json\JsonNode::parse('{"$ref": "http://x/y"}')))->remote() // => true
     */
    public function remote(?JsonNode $node = null): bool
    {
        $node ??= $this->root;
        if ($node->type === JsonKind::Object && is_array($node->value) && isset($node->value['$ref']) && $node->value['$ref']->type === JsonKind::String && !str_starts_with($node->value['$ref']->scalar(), '#')) {
            return true;
        }
        foreach ($node->children() as $child) {
            if ($this->remote($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates a document: null when it is valid, else the failed keyword, the location of its schema and the location of the value.
     *
     * @return array{string, string, string}|null
     *
     * @example A missing member
     *     (new \MySqlMemory\Value\Json\JsonSchema(\MySqlMemory\Value\Json\JsonNode::parse('{"required": ["a"]}')))->validate(\MySqlMemory\Value\Json\JsonNode::parse('{}')) // => ['required', '#', '#']
     */
    public function validate(JsonNode $document): ?array
    {
        return $this->check($this->root, '#', $document, '#');
    }

    /**
     * Checks a value against a schema at their locations.
     *
     * @return array{string, string, string}|null
     */
    public function check(JsonNode $schema, string $at, JsonNode $value, string $where): ?array
    {
        $keywords = self::keywords($schema);
        if (isset($keywords['$ref']) && $keywords['$ref']->type === JsonKind::String) {
            $target = $this->pointer($keywords['$ref']->scalar());

            return $target === null ? null : $this->check($target[0], $target[1], $value, $where);
        }
        if (isset($keywords['type']) && !self::typed($keywords['type'], $value)) {
            return ['type', $at, $where];
        }
        $failure = match ($value->type) {
            JsonKind::Integer, JsonKind::Unsigned, JsonKind::Double, JsonKind::Decimal => $this->number($keywords, $at, $value, $where),
            JsonKind::String => $this->string($keywords, $at, $value, $where),
            JsonKind::Object => $this->object($keywords, $at, $value, $where),
            JsonKind::Array => $this->array($keywords, $at, $value, $where),
            JsonKind::Null, JsonKind::Boolean, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => null,
        };

        return $failure ?? $this->combined($keywords, $at, $value, $where);
    }

    /**
     * Answers the keywords of a schema by name; a schema that is no object has none.
     *
     * @return array<int|string, JsonNode>
     */
    public static function keywords(JsonNode $schema): array
    {
        return $schema->type === JsonKind::Object && is_array($schema->value) ? $schema->value : [];
    }

    /**
     * Follows a `$ref` pointer in the schema: the schema it names and its location, or null when it names none.
     *
     * @return array{JsonNode, string}|null
     */
    public function pointer(string $reference): ?array
    {
        $node = $this->root;
        $steps = array_values(array_filter(explode('/', substr($reference, 1)), static fn (string $step): bool => $step !== ''));
        foreach ($steps as $step) {
            $step = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($step));
            $children = is_array($node->value) ? $node->value : [];
            if (!isset($children[$step])) {
                return null;
            }
            $node = $children[$step];
        }

        return [$node, '#' . ($steps === [] ? '' : '/' . implode('/', $steps))];
    }

    /**
     * Tells whether a value has a type `type` names: one type or an array of them.
     */
    public static function typed(JsonNode $type, JsonNode $value): bool
    {
        foreach ($type->type === JsonKind::Array ? $type->children() : [$type] as $name) {
            $matched = match ($name->type === JsonKind::String ? $name->scalar() : '') {
                'null' => $value->type === JsonKind::Null,
                'boolean' => $value->type === JsonKind::Boolean,
                'object' => $value->type === JsonKind::Object,
                'array' => $value->type === JsonKind::Array,
                'string' => $value->type === JsonKind::String,
                'number' => $value->type->numeric(),
                'integer' => $value->type === JsonKind::Integer || $value->type === JsonKind::Unsigned,
                default => false,
            };
            if ($matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks the keywords of a number: minimum, maximum and multipleOf.
     *
     * @param array<int|string, JsonNode> $keywords
     * @return array{string, string, string}|null
     */
    public function number(array $keywords, string $at, JsonNode $value, string $where): ?array
    {
        $number = JsonNode::number($value);
        foreach (['minimum' => [1, 'exclusiveMinimum'], 'maximum' => [-1, 'exclusiveMaximum']] as $keyword => [$side, $exclusive]) {
            $limit = $keywords[$keyword] ?? null;
            if ($limit === null || !$limit->type->numeric()) {
                continue;
            }
            $compared = Decimal::compare($number, JsonNode::number($limit)) * $side;
            $strict = ($keywords[$exclusive] ?? null)?->value === true;
            if ($compared < 0 || ($strict && $compared === 0)) {
                return [$keyword, $at, $where];
            }
        }
        $divisor = $keywords['multipleOf'] ?? null;
        if ($divisor !== null && $divisor->type->numeric() && Decimal::compare(JsonNode::number($divisor), '0') > 0) {
            $scale = max(Decimal::scale($number), Decimal::scale(JsonNode::number($divisor)));
            if (bccomp(bcmod($number, JsonNode::number($divisor), $scale), '0', $scale) !== 0) {
                return ['multipleOf', $at, $where];
            }
        }

        return null;
    }

    /**
     * Checks the keywords of a string: minLength, maxLength and pattern.
     *
     * @param array<int|string, JsonNode> $keywords
     * @return array{string, string, string}|null
     */
    public function string(array $keywords, string $at, JsonNode $value, string $where): ?array
    {
        $length = mb_strlen($value->scalar(), 'UTF-8');
        $minimum = self::count($keywords['minLength'] ?? null);
        if ($minimum !== null && $length < $minimum) {
            return ['minLength', $at, $where];
        }
        $maximum = self::count($keywords['maxLength'] ?? null);
        if ($maximum !== null && $length > $maximum) {
            return ['maxLength', $at, $where];
        }
        $pattern = $keywords['pattern'] ?? null;
        if ($pattern !== null && $pattern->type === JsonKind::String && self::matches($pattern->scalar(), $value->scalar()) === false) {
            return ['pattern', $at, $where];
        }

        return null;
    }

    /**
     * Answers whether a regular expression finds a match in a string, or null when it is no regular expression.
     *
     * @example A match inside the string
     *     \MySqlMemory\Value\Json\JsonSchema::matches('b+', 'abbc') // => true
     */
    public static function matches(string $pattern, string $subject): ?bool
    {
        set_error_handler(static fn (): bool => true);
        try {
            $result = preg_match('/' . str_replace('/', '\\/', $pattern) . '/u', $subject);
        } finally {
            restore_error_handler();
        }

        return $result === false ? null : $result === 1;
    }

    /**
     * Answers the non-negative integer a counting keyword holds, or null when it holds none.
     */
    public static function count(?JsonNode $keyword): ?int
    {
        if ($keyword === null || ($keyword->type !== JsonKind::Integer && $keyword->type !== JsonKind::Unsigned) || str_starts_with($keyword->scalar(), '-')) {
            return null;
        }

        return (int) $keyword->scalar();
    }

    /**
     * Checks the members of an object and then its keywords: required, minProperties, maxProperties and dependencies.
     *
     * @param array<int|string, JsonNode> $keywords
     * @return array{string, string, string}|null
     */
    public function object(array $keywords, string $at, JsonNode $value, string $where): ?array
    {
        foreach (is_array($value->value) ? $value->value : [] as $name => $member) {
            $failure = $this->member($keywords, $at, (string) $name, $member, $where . '/' . $name);
            if ($failure !== null) {
                return $failure;
            }
        }

        return $this->properties($keywords, $at, $value, $where);
    }

    /**
     * Checks a member of an object: against its `properties` schema and the `patternProperties` schemas its name matches, else against `additionalProperties`.
     *
     * @param array<int|string, JsonNode> $keywords
     * @return array{string, string, string}|null
     */
    public function member(array $keywords, string $at, string $name, JsonNode $member, string $location): ?array
    {
        $properties = self::keywords($keywords['properties'] ?? new JsonNode(JsonKind::Null));
        $additional = $keywords['additionalProperties'] ?? null;
        $matched = array_values(array_filter(self::keywords($keywords['patternProperties'] ?? new JsonNode(JsonKind::Null)), static fn (int|string $pattern): bool => self::matches((string) $pattern, $name) === true, ARRAY_FILTER_USE_KEY));
        if (!isset($properties[$name]) && $matched === [] && $additional?->value === false) {
            return ['additionalProperties', $at, $location];
        }
        foreach ($matched === [] ? [] : (isset($properties[$name]) ? [$properties[$name], ...$matched] : $matched) as $schema) {
            if ($this->check($schema, $at, $member, $location) !== null) {
                return ['patternProperties', $at, $location];
            }
        }

        return match (true) {
            $matched !== [] => null,
            isset($properties[$name]) => $this->check($properties[$name], $at . '/properties/' . $name, $member, $location),
            $additional?->type === JsonKind::Object => $this->check($additional, $at . '/additionalProperties', $member, $location),
            default => null,
        };
    }

    /**
     * Checks the keywords of an object as a whole: required, minProperties, maxProperties and dependencies.
     *
     * @param array<int|string, JsonNode> $keywords
     * @return array{string, string, string}|null
     */
    public function properties(array $keywords, string $at, JsonNode $value, string $where): ?array
    {
        $members = is_array($value->value) ? $value->value : [];
        foreach (($keywords['required'] ?? null)?->type === JsonKind::Array ? $keywords['required']->children() : [] as $required) {
            if ($required->type === JsonKind::String && !isset($members[$required->scalar()])) {
                return ['required', $at, $where];
            }
        }
        $minimum = self::count($keywords['minProperties'] ?? null);
        if ($minimum !== null && count($members) < $minimum) {
            return ['minProperties', $at, $where];
        }
        $maximum = self::count($keywords['maxProperties'] ?? null);
        if ($maximum !== null && count($members) > $maximum) {
            return ['maxProperties', $at, $where];
        }
        foreach (self::keywords($keywords['dependencies'] ?? new JsonNode(JsonKind::Null)) as $name => $dependency) {
            if (!isset($members[$name])) {
                continue;
            }
            $missing = $dependency->type === JsonKind::Array && array_filter($dependency->children(), static fn (JsonNode $needed): bool => $needed->type === JsonKind::String && !isset($members[$needed->scalar()])) !== [];
            if ($missing || ($dependency->type === JsonKind::Object && $this->check($dependency, $at, $value, $where) !== null)) {
                return ['dependencies', $at, $where];
            }
        }

        return null;
    }

    /**
     * Checks the elements of an array and then its keywords: minItems and maxItems.
     *
     * @param array<int|string, JsonNode> $keywords
     * @return array{string, string, string}|null
     */
    public function array(array $keywords, string $at, JsonNode $value, string $where): ?array
    {
        $elements = $value->children();
        $items = $keywords['items'] ?? null;
        $additional = $keywords['additionalItems'] ?? null;
        $unique = ($keywords['uniqueItems'] ?? null)?->value === true;
        foreach ($elements as $index => $element) {
            $location = $where . '/' . $index;
            $failure = null;
            if ($items?->type === JsonKind::Object) {
                $failure = $this->check($items, $at . '/items', $element, $location);
            } elseif ($items?->type === JsonKind::Array) {
                $tuple = $items->children();
                if (isset($tuple[$index])) {
                    $failure = $this->check($tuple[$index], $at . '/items/' . $index, $element, $location);
                } elseif ($additional?->value === false) {
                    $failure = ['additionalItems', $at, $location];
                } elseif ($additional?->type === JsonKind::Object) {
                    $failure = $this->check($additional, $at . '/additionalItems', $element, $location);
                }
            }
            if ($failure !== null) {
                return $failure;
            }
            if ($unique) {
                foreach (array_slice($elements, 0, $index) as $earlier) {
                    if ($earlier->equals($element)) {
                        return ['uniqueItems', $at, $location];
                    }
                }
            }
        }
        $minimum = self::count($keywords['minItems'] ?? null);
        if ($minimum !== null && count($elements) < $minimum) {
            return ['minItems', $at, $where];
        }
        $maximum = self::count($keywords['maxItems'] ?? null);

        return $maximum !== null && count($elements) > $maximum ? ['maxItems', $at, $where] : null;
    }

    /**
     * Checks the keywords of any value: enum, allOf, anyOf, oneOf and not.
     *
     * @param array<int|string, JsonNode> $keywords
     * @return array{string, string, string}|null
     */
    public function combined(array $keywords, string $at, JsonNode $value, string $where): ?array
    {
        $enum = $keywords['enum'] ?? null;
        if ($enum?->type === JsonKind::Array && array_filter($enum->children(), static fn (JsonNode $allowed): bool => $allowed->equals($value)) === []) {
            return ['enum', $at, $where];
        }
        $valid = [];
        foreach (['allOf', 'anyOf', 'oneOf'] as $keyword) {
            $schemas = ($keywords[$keyword] ?? null)?->type === JsonKind::Array ? $keywords[$keyword]->children() : null;
            if ($schemas === null) {
                continue;
            }
            $valid[$keyword] = count(array_filter(array_keys($schemas), fn (int $index): bool => $this->check($schemas[$index], $at . '/' . $keyword . '/' . $index, $value, $where) === null));
            $failed = match ($keyword) {
                'allOf' => $valid[$keyword] !== count($schemas),
                'anyOf' => $valid[$keyword] === 0,
                'oneOf' => $valid[$keyword] !== 1,
            };
            if ($failed) {
                return [$keyword, $at, $where];
            }
        }
        $not = $keywords['not'] ?? null;

        return $not?->type === JsonKind::Object && $this->check($not, $at . '/not', $value, $where) === null ? ['not', $at, $where] : null;
    }

    /**
     * Writes the report JSON_SCHEMA_VALIDATION_REPORT answers for the result of a validation.
     *
     * @param array{string, string, string}|null $failure
     *
     * @example A failure
     *     \MySqlMemory\Value\Json\JsonSchema::report(['type', '#', '#'])->text() // => '{"valid": false, "reason": "The JSON document location \'#\' failed requirement \'type\' at JSON Schema location \'#\'", "schema-location": "#", "document-location": "#", "schema-failed-keyword": "type"}'
     */
    public static function report(?array $failure): JsonNode
    {
        if ($failure === null) {
            return new JsonNode(JsonKind::Object, ['valid' => new JsonNode(JsonKind::Boolean, true)]);
        }
        [$keyword, $schema, $document] = $failure;

        return new JsonNode(JsonKind::Object, [
            'valid' => new JsonNode(JsonKind::Boolean, false),
            'reason' => new JsonNode(JsonKind::String, "The JSON document location '" . $document . "' failed requirement '" . $keyword . "' at JSON Schema location '" . $schema . "'"),
            'schema-location' => new JsonNode(JsonKind::String, $schema),
            'document-location' => new JsonNode(JsonKind::String, $document),
            'schema-failed-keyword' => new JsonNode(JsonKind::String, $keyword),
        ]);
    }
}
