<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Json;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonPath;
use Override;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;

/**
 * A call of JSON_VALUE(document, path [RETURNING type] [ON EMPTY] [ON ERROR]): the value at the path, in the RETURNING type.
 *
 * The document is NULL for NULL. When the path selects nothing, the ON EMPTY response answers:
 * NULL by default, its DEFAULT value, or ER_MISSING_JSON_VALUE. A document that is no JSON text,
 * a path that selects more than one value, and a value that does not convert to the type exactly
 * are errors the ON ERROR response answers: NULL by default, with the error of the document as a
 * warning, its DEFAULT value, or the error (ER_MULTIPLE_JSON_VALUES, or ER_DATA_OUT_OF_RANGE
 * naming the type). A JSON null is NULL; any other value converts as {@see Returning} says, an
 * array or an object to a string as its JSON text (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#function_json-value.
 *
 * @visibility MySqlMemory
 */
final class ValueCall implements Evaluable
{
    /**
     * @param Evaluable $document The document
     * @param JsonPath $path The path
     * @param Domain $domain The RETURNING type
     * @param array{JsonResponseKind, Evaluable|null} $empty The ON EMPTY response and its DEFAULT value converted to the type
     * @param array{JsonResponseKind, Evaluable|null} $error The ON ERROR response and its DEFAULT value converted to the type
     */
    public function __construct(public readonly Evaluable $document, public readonly JsonPath $path, public readonly Domain $domain, public readonly array $empty, public readonly array $error)
    {
    }

    /**
     * Answers the RETURNING type.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Answers the value at the path for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        try {
            $document = Jsons::read($this->document, $frame, 1, 'json_value');
        } catch (SqlError $failure) {
            if ($failure->error !== DataError::InvalidJsonTextInParameter || $this->error[0] === JsonResponseKind::Error) {
                throw $failure;
            }
            $frame->context->diagnostics->warning(DataError::InvalidJsonTextInParameter, $failure->getMessage());

            return $this->respond($this->error, $frame);
        }
        if ($document === null) {
            return null;
        }
        $found = $this->path->select($document);
        if ($found === []) {
            if ($this->empty[0] === JsonResponseKind::Error) {
                throw DataError::MissingJsonValue->error('json_value');
            }

            return $this->respond($this->empty, $frame);
        }
        if (count($found) > 1) {
            if ($this->error[0] === JsonResponseKind::Error) {
                throw DataError::MultipleJsonValues->error('json_value');
            }

            return $this->respond($this->error, $frame);
        }
        if ($found[0]->type === JsonKind::Null) {
            return null;
        }
        $returning = new Returning($this->domain);
        [$converted, $value] = $returning->coerce($found[0], true);
        if ($converted) {
            return $value;
        }
        if ($this->error[0] === JsonResponseKind::Error) {
            throw DataError::DataOutOfRange->error($returning->name(), 'json_value');
        }

        return $this->respond($this->error, $frame);
    }

    /**
     * Answers what a NULL or DEFAULT response answers.
     *
     * @param array{JsonResponseKind, Evaluable|null} $response
     *
     * @throws SqlError When the DEFAULT value cannot be evaluated
     */
    public function respond(array $response, Frame $frame): int|float|string|null
    {
        return $response[1]?->evaluate($frame);
    }
}
