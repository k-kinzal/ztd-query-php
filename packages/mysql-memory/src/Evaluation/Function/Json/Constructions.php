<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Json;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Json\Json;
use MySqlMemory\Value\Json\JsonEdit;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonSyntax;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The functions that build JSON values and tell what a JSON value is: JSON_ARRAY, JSON_OBJECT,
 * JSON_QUOTE, JSON_UNQUOTE, JSON_TYPE, JSON_VALID, JSON_LENGTH, JSON_DEPTH and JSON_KEYS.
 *
 * An argument that becomes a value of a document is made a JSON value from its SQL type
 * ({@see Jsons::value()}); NULL is the JSON null. A name of JSON_OBJECT is the text of its
 * value; a NULL name is refused (ER_JSON_DOCUMENT_NULL_KEY) and so is a binary string
 * (ER_INVALID_JSON_CHARSET); of members with one name the last is kept, the first in MySQL 5.7. JSON_QUOTE takes a
 * string only (ER_INCORRECT_TYPE). JSON_UNQUOTE reads a string that starts and ends with a
 * quotation mark as a JSON string, and answers any other string as it is. JSON_VALID is 0 for a
 * value that is no string. JSON_LENGTH counts the elements of an array, the members of an object,
 * and 1 for a scalar; with a path, it counts what the path selects, which is the array of the
 * values a wild path selects (MySQL 5.7 refuses a wild path), and is NULL when the path selects nothing. JSON_KEYS answers the
 * names of an object in the order the server writes them, and NULL for another value; its path may
 * not be wild (ER_INVALID_JSON_PATH_WILDCARD). A NULL document or path makes each function NULL;
 * the document is read before the path (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-creation-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/json-attribute-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/json-modification-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Constructions
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('JSON_ARRAY', 0, -1, $this->array(...)),
            new Routine('JSON_OBJECT', 0, -1, $this->object(...), 2),
            new Routine('JSON_QUOTE', 1, 1, $this->quote(...)),
            new Routine('JSON_UNQUOTE', 1, 1, $this->unquote(...)),
            new Routine('JSON_TYPE', 1, 1, static fn (Frame $f, array $a): ?string => Jsons::read($a[0], $f, 1, 'json_type')?->name()),
            new Routine('JSON_VALID', 1, 1, $this->valid(...)),
            new Routine('JSON_LENGTH', 1, 2, $this->length(...)),
            new Routine('JSON_DEPTH', 1, 1, static fn (Frame $f, array $a): ?int => Jsons::read($a[0], $f, 1, 'json_depth')?->depth()),
            new Routine('JSON_KEYS', 1, 2, $this->keys(...)),
        ];
    }

    /**
     * JSON_ARRAY(value, ...): an array of the values.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When an argument cannot be evaluated
     */
    public function array(Frame $frame, array $arguments): string
    {
        return self::deep(new JsonNode(JsonKind::Array, array_map(static fn (Evaluable $argument): JsonNode => Jsons::argument($argument, $frame), $arguments)))->store();
    }

    /**
     * JSON_OBJECT(name, value, ...): an object of the members.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When a name is NULL or a binary string
     */
    public function object(Frame $frame, array $arguments): string
    {
        $members = [];
        for ($index = 0; $index < count($arguments); $index += 2) {
            $name = $arguments[$index]->evaluate($frame);
            if ($name === null) {
                throw DataError::JsonDocumentNullKey->error();
            }
            $key = self::name($name, $arguments[$index]);
            $member = Jsons::argument($arguments[$index + 1], $frame);
            if (!isset($members[$key]) || $frame->context->modes->release !== GrammarRelease::MySql5744) {
                $members[$key] = $member;
            }
        }

        return self::deep(JsonEdit::object($members))->store();
    }

    /**
     * Answers a document that nests no deeper than a document may.
     *
     * @throws SqlError When arrays and objects nest more than 100 deep (ER_JSON_DOCUMENT_TOO_DEEP)
     */
    public static function deep(JsonNode $document): JsonNode
    {
        if ($document->depth() > Json::MAX_DEPTH) {
            throw DataError::JsonDocumentTooDeep->error();
        }

        return $document;
    }

    /**
     * Answers the name of a member a value of an argument gives: its text, in utf8mb4.
     *
     * @throws SqlError When the value is a binary string
     */
    public static function name(int|float|string $value, Evaluable $argument): string
    {
        $domain = $argument->domain();
        if ($domain->kind === Kind::Json) {
            return Json::visible((string) $value);
        }
        $text = (string) Convert::toText($value, $domain);
        if ($domain->kind !== Kind::String) {
            return $text;
        }
        if ($domain->collation->charset === Charset::binary()) {
            throw DataError::InvalidJsonCharset->error('binary');
        }

        return Encoding::convert($text, $domain->collation->charset, Charset::known('utf8mb4'));
    }

    /**
     * JSON_QUOTE(string): the string as a JSON string.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the argument is no string, or a binary string
     */
    public function quote(Frame $frame, array $arguments): ?string
    {
        $domain = $arguments[0]->domain();
        if ($domain->kind !== Kind::String && $domain->kind !== Kind::Null) {
            throw DataError::IncorrectType->error(1, 'json_quote');
        }
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        if ($domain->collation->charset === Charset::binary()) {
            throw DataError::InvalidJsonCharset->error('binary');
        }

        return Json::quote(Encoding::convert((string) $value, $domain->collation->charset, Charset::known('utf8mb4')));
    }

    /**
     * JSON_UNQUOTE(value): the characters of a JSON string, the text of another JSON value, or a string as it is.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the argument is of another type, a binary string, or a quoted string that is no JSON string
     */
    public function unquote(Frame $frame, array $arguments): ?string
    {
        $domain = $arguments[0]->domain();
        if ($domain->kind !== Kind::String && $domain->kind !== Kind::Json && $domain->kind !== Kind::Null) {
            throw DataError::IncorrectType->error(1, 'json_unquote');
        }
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        if ($domain->kind === Kind::Json) {
            return JsonNode::load((string) $value)->unquoted();
        }
        if ($domain->collation->charset === Charset::binary()) {
            throw DataError::InvalidJsonCharset->error('binary');
        }
        $text = Encoding::convert((string) $value, $domain->collation->charset, Charset::known('utf8mb4'));
        if (strlen($text) < 2 || $text[0] !== '"' || $text[strlen($text) - 1] !== '"') {
            return $text;
        }

        return Jsons::parse($text, 1, 'json_unquote')->unquoted();
    }

    /**
     * JSON_VALID(value): whether the value is a JSON value or a string holding a JSON text.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the argument cannot be evaluated
     */
    public function valid(Frame $frame, array $arguments): ?int
    {
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $arguments[0]->domain();
        if ($domain->kind === Kind::Json) {
            return 1;
        }
        if ($domain->kind !== Kind::String || $domain->collation->charset === Charset::binary()) {
            return 0;
        }
        try {
            Json::canonical(Encoding::convert((string) $value, $domain->collation->charset, Charset::known('utf8mb4')));
        } catch (JsonSyntax) {
            return 0;
        }

        return 1;
    }

    /**
     * JSON_LENGTH(document [, path]): the number of values the document, or what the path selects, holds.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the document or the path is not valid
     */
    public function length(Frame $frame, array $arguments): ?int
    {
        $document = Jsons::read($arguments[0], $frame, 1, 'json_length');
        if ($document === null) {
            return null;
        }
        if (isset($arguments[1])) {
            $path = Jsons::path($arguments[1], $frame);
            if ($path !== null && $frame->context->modes->release === GrammarRelease::MySql5744) {
                Modifications::single($path, $frame);
            }
            $document = $path?->extract($document);
            if ($document === null) {
                return null;
            }
        }

        return $document->type === JsonKind::Array || $document->type === JsonKind::Object ? count($document->children()) : 1;
    }

    /**
     * JSON_KEYS(document [, path]): an array of the names of the object, or of the object the path selects.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the document or the path is not valid, or the path is wild
     */
    public function keys(Frame $frame, array $arguments): ?string
    {
        $document = Jsons::read($arguments[0], $frame, 1, 'json_keys');
        if ($document === null) {
            return null;
        }
        if (isset($arguments[1])) {
            $path = Jsons::path($arguments[1], $frame);
            if ($path === null) {
                return null;
            }
            Modifications::single($path, $frame);
            $document = $path->extract($document);
        }
        if ($document === null || $document->type !== JsonKind::Object || !is_array($document->value)) {
            return null;
        }

        return (new JsonNode(JsonKind::Array, array_map(static fn (int|string $name): JsonNode => new JsonNode(JsonKind::String, (string) $name), array_keys($document->value))))->text();
    }
}
