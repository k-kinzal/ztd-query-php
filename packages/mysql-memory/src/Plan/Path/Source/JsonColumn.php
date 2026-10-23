<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Source;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Function\Json\Returning;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * One column of JSON_TABLE, ready to compute its values: a counter, a value at a path, whether a path finds something, or nested columns.
 *
 * A value at a path is NULL when the path finds a JSON null. When the path finds nothing the
 * ON EMPTY response answers, and when it finds more than one value, an array or an object for a
 * column that is not JSON, or a value that does not convert to the column type exactly
 * ({@see Returning}), the ON ERROR response answers; each answers NULL by default, its DEFAULT
 * value, or its error: ER_MISSING_JSON_TABLE_VALUE, ER_WRONG_JSON_TABLE_VALUE for an array or an
 * object, ER_JT_VALUE_OUT_OF_RANGE for the rest. An EXISTS column is 1 or 0 in its type (verified
 * on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 *
 * @visibility MySqlMemory
 */
final class JsonColumn
{
    /**
     * @param string $kind The kind of the column: 'ordinality', 'path', 'exists' or 'nested'
     * @param string $name The column name, which errors quote
     * @param Domain $domain The type of a counter, value or EXISTS column
     * @param JsonPath|null $path The path of a value, EXISTS or nested column
     * @param array{JsonResponseKind, JsonNode|null} $empty The ON EMPTY response and its DEFAULT value
     * @param array{JsonResponseKind, JsonNode|null} $error The ON ERROR response and its DEFAULT value
     * @param list<JsonColumn> $columns The columns of a nested path
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $name,
        public readonly Domain $domain,
        public readonly ?JsonPath $path = null,
        public readonly array $empty = [JsonResponseKind::Null, null],
        public readonly array $error = [JsonResponseKind::Null, null],
        public readonly array $columns = [],
    ) {
    }

    /**
     * Answers the number of values the column contributes to a row: one, or those of its nested columns.
     */
    public function width(): int
    {
        if ($this->kind !== 'nested') {
            return 1;
        }

        return array_sum(array_map(static fn (self $column): int => $column->width(), $this->columns));
    }

    /**
     * Computes the value of a value or EXISTS column for the value a row path selected; a value too long or out of range for an ERROR response is first warned of, as storing it is.
     *
     * @throws SqlError When a response is ERROR, or a DEFAULT value does not convert
     */
    public function value(JsonNode $selected, Context $context): int|float|string|null
    {
        $found = $this->path?->select($selected) ?? [];
        if ($this->kind === 'exists') {
            return (new Returning($this->domain))->coerce(new JsonNode(JsonKind::Integer, $found === [] ? '0' : '1'), false)[1];
        }
        if ($found === []) {
            return $this->respond($this->empty, DataError::MissingJsonTableValue);
        }
        if (count($found) > 1 || ($this->domain->kind !== Kind::Json && in_array($found[0]->type, [JsonKind::Array, JsonKind::Object], true))) {
            return $this->respond($this->error, DataError::WrongJsonTableValue);
        }
        if ($found[0]->type === JsonKind::Null) {
            return null;
        }
        [$converted, $value] = (new Returning($this->domain, true))->coerce($found[0], false);
        if ($converted) {
            return $value;
        }
        if ($this->error[0] === JsonResponseKind::Error) {
            $this->fail($found[0], $context);
        }

        return $this->respond($this->error, DataError::JsonTableValueOutOfRange);
    }

    /**
     * Raises the error of a value that does not convert to a number column under ERROR ON ERROR, or warns as storing it does before the error of the response.
     *
     * A value that is no number is ER_INVALID_JSON_VALUE_FOR_CAST, and an integer beyond the 64-bit
     * range or a decimal beyond 65 digits ER_NUMERIC_JSON_VALUE_OUT_OF_RANGE, both naming the type read and the column; a value out of
     * the range of the column, or a string too long for it, is warned of (verified on a live 8.4 server).
     *
     * @throws SqlError When the value is no number or beyond the 64-bit range
     */
    public function fail(JsonNode $node, Context $context): void
    {
        $target = match ($this->domain->kind) {
            Kind::Integer, Kind::Year => 'INTEGER',
            Kind::Decimal => 'DECIMAL',
            Kind::Double => 'DOUBLE',
            Kind::String, Kind::Json, Kind::Date, Kind::Time, Kind::DateTime, Kind::Bit, Kind::Null => '',
        };
        if ($target === '') {
            if ($this->domain->kind === Kind::String) {
                $context->diagnostics->warning(DataError::DataTooLong, DataError::DataTooLong->message($this->name, 1));
            }

            return;
        }
        $pattern = $target === 'INTEGER' ? '/\A[ \t\n\r]*[+-]?[0-9]+\z/' : '/\A[ \t\n\r]*[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?[ \t\n\r]*\z/';
        if (!$node->type->numeric() && $node->type !== JsonKind::Boolean && ($node->type !== JsonKind::String || preg_match($pattern, $node->scalar()) !== 1)) {
            throw DataError::InvalidJsonValueForCast->error($target, '', $this->name, 1);
        }
        $number = $node->type === JsonKind::String ? trim($node->scalar()) : ($node->type === JsonKind::Boolean ? '0' : JsonNode::number($node));
        $whole = Decimal::numeric(Decimal::canonical($number));
        $beyond = $target === 'INTEGER' ? bccomp($whole, '18446744073709551615', 0) > 0 || bccomp($whole, '-9223372036854775808', 0) < 0 : $target === 'DECIMAL' && Decimal::integerDigits($whole) > 65;
        if ($beyond) {
            throw DataError::NumericJsonValueOutOfRange->error($target, '', $this->name, 1);
        }
        $context->diagnostics->warning(DataError::OutOfRange, DataError::OutOfRange->message($this->name, 1));
    }

    /**
     * Answers what an ON EMPTY or ON ERROR response answers.
     *
     * @param array{JsonResponseKind, JsonNode|null} $response
     * @param DataError $failure The error an ERROR response raises
     *
     * @throws SqlError When the response is ERROR, or its DEFAULT value does not convert
     */
    public function respond(array $response, DataError $failure): int|float|string|null
    {
        if ($response[0] === JsonResponseKind::Error) {
            throw $failure->error($this->name);
        }
        if ($response[1] === null || $response[1]->type === JsonKind::Null) {
            return null;
        }
        [$converted, $value] = (new Returning($this->domain))->coerce($response[1], false);
        if (!$converted) {
            throw DataError::InvalidJsonValueForCast->error((new Returning($this->domain))->name() === 'SIGNED' ? 'INTEGER' : (new Returning($this->domain))->name(), '', $this->name, 1);
        }

        return $value;
    }
}
