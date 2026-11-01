<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Json;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonSchema;

/**
 * The JSON Schema functions: JSON_SCHEMA_VALID and JSON_SCHEMA_VALIDATION_REPORT.
 *
 * The schema is read first and must be an object (ER_INVALID_JSON_TYPE); a schema that refers to
 * a document outside itself is not supported (ER_NOT_SUPPORTED_YET). The document is validated as
 * its JSON text reads, so a temporal or opaque value is a string. A NULL schema or document makes
 * each function NULL ({@see JsonSchema}) (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-validation-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Schemas
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('JSON_SCHEMA_VALID', 2, 2, fn (Frame $f, array $a): ?int => ($failure = $this->validate($f, $a, 'json_schema_valid')) === false ? null : ($failure === null ? 1 : 0)),
            new Routine('JSON_SCHEMA_VALIDATION_REPORT', 2, 2, fn (Frame $f, array $a): ?string => ($failure = $this->validate($f, $a, 'json_schema_validation_report')) === false ? null : JsonSchema::report($failure)->text()),
        ];
    }

    /**
     * Validates the document against the schema: false when either is NULL, null when it is valid, else the failure.
     *
     * @param list<Evaluable> $arguments
     * @param string $function The function name the server writes in its messages
     * @return array{string, string, string}|false|null
     *
     * @throws SqlError When an argument is no JSON document, the schema is no object, or it refers outside itself
     */
    public function validate(Frame $frame, array $arguments, string $function): array|false|null
    {
        $schema = Jsons::read($arguments[0], $frame, 1, $function);
        if ($schema !== null && $schema->type !== JsonKind::Object) {
            throw DataError::InvalidJsonTypeRequired->error(1, $function, 'object');
        }
        $document = Jsons::read($arguments[1], $frame, 2, $function);
        if ($schema === null || $document === null) {
            return false;
        }
        $validator = new JsonSchema($schema);
        if ($validator->remote()) {
            throw StatementError::NotSupportedYet->error('references in JSON Schema');
        }

        return $validator->validate(JsonNode::parse($document->text()));
    }
}
