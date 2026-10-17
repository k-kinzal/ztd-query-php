<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Value\Json\Json;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonSyntax;

/**
 * The attributes of an account: a JSON object that ATTRIBUTE merges into and COMMENT sets the `comment` member of.
 *
 * ATTRIBUTE merges its object as JSON_MERGE_PATCH() does: a member set to null is removed, an
 * object merges into an object, and any other value replaces the member. A string that is not a
 * JSON object is ER_INVALID_USER_ATTRIBUTE_JSON.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-comments-attributes.
 *
 * @visibility MySqlMemory
 * @example Merging an attribute and a comment
 *     (new \MySqlMemory\Account\Attributes())->comment((new \MySqlMemory\Account\Attributes())->merge(null, '{"b": 1}'), 'x') // => '{"b": 1, "comment": "x"}'
 */
final class Attributes
{
    /**
     * Merges an ATTRIBUTE object into the attributes of an account.
     *
     * @param string|null $current The attributes as the server writes them, or null for none
     *
     * @throws SqlError When the text is not a JSON object
     */
    public function merge(?string $current, string $patch): string
    {
        try {
            $node = JsonNode::parse($patch);
        } catch (JsonSyntax $failure) {
            throw new SqlError(AccountError::InvalidUserAttributeJson, AccountError::InvalidUserAttributeJson->message(), $failure);
        }
        if ($node->type !== JsonKind::Object) {
            throw AccountError::InvalidUserAttributeJson->error();
        }

        return $this->text($this->patch($current === null ? new JsonNode(JsonKind::Object, []) : JsonNode::parse($current), $node));
    }

    /**
     * Sets the comment of an account.
     *
     * @param string|null $current The attributes as the server writes them, or null for none
     */
    public function comment(?string $current, string $comment): string
    {
        return $this->text($this->patch($current === null ? new JsonNode(JsonKind::Object, []) : JsonNode::parse($current), new JsonNode(JsonKind::Object, ['comment' => new JsonNode(JsonKind::String, $comment)])));
    }

    /**
     * Merges a patch into a value as JSON_MERGE_PATCH() does.
     */
    public function patch(JsonNode $target, JsonNode $patch): JsonNode
    {
        if ($patch->type !== JsonKind::Object) {
            return $patch;
        }
        $members = $target->type === JsonKind::Object && is_array($target->value) ? $target->value : [];
        foreach (is_array($patch->value) ? $patch->value : [] as $name => $value) {
            if ($value->type === JsonKind::Null) {
                unset($members[$name]);
                continue;
            }
            $members[$name] = $this->patch($members[$name] ?? new JsonNode(JsonKind::Null), $value);
        }

        return new JsonNode(JsonKind::Object, $members);
    }

    /**
     * Writes a value in the text the server writes, its members ordered.
     */
    public function text(JsonNode $node): string
    {
        return Json::canonical($node->text());
    }
}
