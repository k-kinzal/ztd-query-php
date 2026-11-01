<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Json;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Json\JsonBinary;
use MySqlMemory\Value\Json\JsonPretty;

/**
 * The JSON utility functions that write a document out or measure it: JSON_PRETTY,
 * JSON_STORAGE_SIZE and JSON_STORAGE_FREE.
 *
 * Each takes a JSON value or a string holding a JSON text ({@see Jsons::read()}); NULL makes each
 * NULL. JSON_PRETTY writes the document over indented lines ({@see JsonPretty}); JSON_STORAGE_SIZE
 * answers the bytes of its binary form ({@see JsonBinary}); JSON_STORAGE_FREE answers the space a
 * partial update of a JSON column freed in it, which is 0 for every value here, as values are
 * stored whole (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-utility-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Outputs
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('JSON_PRETTY', 1, 1, $this->pretty(...)),
            new Routine('JSON_STORAGE_SIZE', 1, 1, $this->size(...)),
            new Routine('JSON_STORAGE_FREE', 1, 1, $this->free(...)),
        ];
    }

    /**
     * JSON_PRETTY(document): the document written over indented lines.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the argument is no JSON document
     */
    public function pretty(Frame $frame, array $arguments): ?string
    {
        $document = Jsons::read($arguments[0], $frame, 1, 'json_pretty');

        return $document === null ? null : JsonPretty::text($document);
    }

    /**
     * JSON_STORAGE_SIZE(document): the number of bytes of the binary form of the document.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the argument is no JSON document
     */
    public function size(Frame $frame, array $arguments): ?int
    {
        $document = Jsons::read($arguments[0], $frame, 1, 'json_storage_size');

        return $document === null ? null : JsonBinary::size($document);
    }

    /**
     * JSON_STORAGE_FREE(document): the number of bytes partial updates freed in the document, 0 for a value stored whole.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the argument is no JSON document
     */
    public function free(Frame $frame, array $arguments): ?int
    {
        return Jsons::read($arguments[0], $frame, 1, 'json_storage_free') === null ? null : 0;
    }
}
