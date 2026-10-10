<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Json;

use Closure;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Json\JsonEdit;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonLegKind;
use MySqlMemory\Value\Json\JsonMerge;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The functions that change JSON documents: JSON_SET, JSON_INSERT, JSON_REPLACE, JSON_REMOVE,
 * JSON_ARRAY_APPEND, JSON_ARRAY_INSERT, JSON_MERGE_PATCH, JSON_MERGE_PRESERVE and JSON_MERGE.
 *
 * The functions that take paths and values read the document, then each path and its value in
 * turn, each change seeing the result of the one before ({@see JsonEdit}). A NULL document is
 * NULL before any path is read, and a NULL path is NULL before the paths after it are read; a
 * NULL value is the JSON null, and a predicate a JSON boolean ({@see Jsons::argument()}). A path
 * may not hold `*`, `**` or a range (ER_INVALID_JSON_PATH_WILDCARD), and a path of
 * JSON_ARRAY_INSERT() must end with a cell (ER_INVALID_JSON_PATH_ARRAY_CELL). JSON_REMOVE() reads
 * every path before it removes anything, and refuses `$` then (ER_JSON_VACUOUS_PATH).
 *
 * JSON_MERGE_PRESERVE() reads its documents in turn and is NULL at the first NULL one.
 * JSON_MERGE_PATCH() reads every document; a NULL one makes the result NULL until a document that
 * is no object replaces it. JSON_MERGE() is JSON_MERGE_PRESERVE() under its old name, and its
 * messages name json_merge_preserve. A result in which arrays and objects nest more than 100 deep
 * is refused (ER_JSON_DOCUMENT_TOO_DEEP). Release 5.7 words the wildcard message without the
 * range (verified on live 5.7.44 and 8.4 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-modification-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Modifications
{
    /**
     * The deepest arrays and objects may nest in a JSON document.
     */
    public const DEPTH = 100;

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('JSON_SET', 3, -1, fn (Frame $f, array $a): ?string => $this->put($f, $a, 'json_set', true, true), 2),
            new Routine('JSON_INSERT', 3, -1, fn (Frame $f, array $a): ?string => $this->put($f, $a, 'json_insert', true, false), 2),
            new Routine('JSON_REPLACE', 3, -1, fn (Frame $f, array $a): ?string => $this->put($f, $a, 'json_replace', false, true), 2),
            new Routine('JSON_ARRAY_APPEND', 3, -1, $this->append(...), 2),
            new Routine('JSON_ARRAY_INSERT', 3, -1, $this->insert(...), 2),
            new Routine('JSON_REMOVE', 2, -1, $this->remove(...)),
            new Routine('JSON_MERGE_PATCH', 2, -1, $this->patch(...)),
            new Routine('JSON_MERGE_PRESERVE', 2, -1, $this->preserve(...)),
            new Routine('JSON_MERGE', 2, -1, $this->preserve(...)),
        ];
    }

    /**
     * JSON_SET(), JSON_INSERT() and JSON_REPLACE() (document, path, value, ...): the document with each value put at its path.
     *
     * @param list<Evaluable> $arguments
     * @param string $function The function name the server writes in its messages
     * @param bool $create Whether a value is added where there is none
     * @param bool $replace Whether a value that is there is replaced
     *
     * @throws SqlError When the document or a path is not valid, or the result nests too deeply
     */
    public function put(Frame $frame, array $arguments, string $function, bool $create, bool $replace): ?string
    {
        return $this->edit($frame, $arguments, $function, static fn (JsonNode $document, JsonPath $path, JsonNode $value): JsonNode => JsonEdit::put($document, $path, $value, $create, $replace));
    }

    /**
     * JSON_ARRAY_APPEND(document, path, value, ...): the document with each value appended to the array at its path.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the document or a path is not valid, or the result nests too deeply
     */
    public function append(Frame $frame, array $arguments): ?string
    {
        return $this->edit($frame, $arguments, 'json_array_append', JsonEdit::append(...));
    }

    /**
     * JSON_ARRAY_INSERT(document, path, value, ...): the document with each value inserted into an array before the cell its path names.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the document or a path is not valid, a path does not end with a cell, or the result nests too deeply
     */
    public function insert(Frame $frame, array $arguments): ?string
    {
        return $this->edit($frame, $arguments, 'json_array_insert', JsonEdit::insert(...), true);
    }

    /**
     * Reads the document, then puts each value at its path in turn.
     *
     * @param list<Evaluable> $arguments
     * @param string $function The function name the server writes in its messages
     * @param Closure(JsonNode, JsonPath, JsonNode): JsonNode $change Answers the document with a value put at a path
     * @param bool $cell Whether each path must end with a cell
     *
     * @throws SqlError When the document or a path is not valid, or the result nests too deeply
     */
    public function edit(Frame $frame, array $arguments, string $function, Closure $change, bool $cell = false): ?string
    {
        $document = Jsons::read($arguments[0], $frame, 1, $function);
        if ($document === null) {
            return null;
        }
        for ($index = 1; $index < count($arguments); $index += 2) {
            $path = Jsons::path($arguments[$index], $frame);
            if ($path === null) {
                return null;
            }
            self::single($path, $frame);
            $last = $path->legs[count($path->legs) - 1] ?? null;
            if ($cell && $last?->kind !== JsonLegKind::Cell) {
                throw DataError::InvalidJsonPathArrayCell->error();
            }
            $document = $change($document, $path, Jsons::argument($arguments[$index + 1], $frame));
        }

        return self::result($document);
    }

    /**
     * JSON_REMOVE(document, path, ...): the document without the values its paths name, removed in turn.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the document or a path is not valid, or a path is `$`
     */
    public function remove(Frame $frame, array $arguments): ?string
    {
        $document = Jsons::read($arguments[0], $frame, 1, 'json_remove');
        if ($document === null) {
            return null;
        }
        $paths = [];
        foreach (array_slice($arguments, 1) as $argument) {
            $path = Jsons::path($argument, $frame);
            if ($path === null) {
                return null;
            }
            self::single($path, $frame);
            $paths[] = $path;
        }
        foreach ($paths as $path) {
            if ($path->legs === []) {
                throw DataError::JsonVacuousPath->error();
            }
            $document = JsonEdit::remove($document, $path);
        }

        return self::result($document);
    }

    /**
     * JSON_MERGE_PRESERVE() and JSON_MERGE() (document, document, ...): the documents merged in turn, keeping every value.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When a document is not valid, or the result nests too deeply
     */
    public function preserve(Frame $frame, array $arguments): ?string
    {
        $merged = null;
        foreach ($arguments as $index => $argument) {
            $document = Jsons::read($argument, $frame, $index + 1, 'json_merge_preserve');
            if ($document === null) {
                return null;
            }
            $merged = $merged === null ? $document : JsonMerge::preserve($merged, $document);
        }

        return $merged === null ? null : self::result($merged);
    }

    /**
     * JSON_MERGE_PATCH(document, patch, ...): the document with each patch applied in turn.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When a document is not valid, or the result nests too deeply
     */
    public function patch(Frame $frame, array $arguments): ?string
    {
        $merged = Jsons::read($arguments[0], $frame, 1, 'json_merge_patch');
        foreach (array_slice($arguments, 1) as $index => $argument) {
            $patch = Jsons::read($argument, $frame, $index + 2, 'json_merge_patch');
            $merged = match (true) {
                $patch === null => null,
                $patch->type !== JsonKind::Object => $patch,
                $merged === null => null,
                default => JsonMerge::patch($merged, $patch),
            };
        }

        return $merged === null ? null : self::result($merged);
    }

    /**
     * Refuses a path that can name more than one value.
     *
     * @throws SqlError When the path holds `*`, `**` or a range
     */
    public static function single(JsonPath $path, Frame $frame): void
    {
        if (!$path->wild()) {
            return;
        }
        if ($frame->context->modes->release === GrammarRelease::MySql5744) {
            throw new SqlError(DataError::InvalidJsonPathWildcard, 'In this situation, path expressions may not contain the * and ** tokens.');
        }

        throw DataError::InvalidJsonPathWildcard->error();
    }

    /**
     * Answers a changed document as an SQL expression holds it.
     *
     * @throws SqlError When arrays and objects nest more than 100 deep in it
     */
    public static function result(JsonNode $document): string
    {
        if (JsonEdit::nesting($document) > self::DEPTH) {
            throw DataError::JsonDocumentTooDeep->error();
        }

        return $document->store();
    }
}
