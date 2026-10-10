<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Json\Predicate;
use MySqlMemory\Evaluation\Function\Json\ValueCall;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use MySqlMemory\Value\Json\JsonSyntax;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\MemberOf;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles the JSON operators: `col->'path'`, `col->>'path'` and `value MEMBER OF (array)`, and JSON_VALUE().
 *
 * A JSON argument is a JSON value, or a string read as a JSON text after it is converted to
 * utf8mb4; a value of another type is refused for each row, even NULL (ER_INVALID_TYPE_FOR_JSON),
 * a binary string is refused (ER_INVALID_JSON_CHARSET, followed by ER_INVALID_TYPE_FOR_JSON), and a text that is no JSON document is
 * refused with the message of the parser (ER_INVALID_JSON_TEXT_IN_PARAM). `->` is
 * JSON_EXTRACT() and `->>` JSON_UNQUOTE(JSON_EXTRACT()); the path is read once the document is,
 * so a NULL document hides a path that is not valid. MEMBER OF is NULL when its value is NULL,
 * before the array is checked, or when the array is NULL; it tells whether the value equals an
 * element of the array, or the array itself when it is no array. The value is a JSON value made
 * from its SQL type: a number keeps its type, a string is a JSON string, a binary string an opaque
 * value, a temporal value a JSON temporal value, and a predicate a JSON boolean (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Jsons
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles `col->'path'` and `col->>'path'`.
     *
     * @throws SqlError When the column cannot be compiled
     */
    public function extraction(JsonExtraction $node, Scope $scope): Evaluable
    {
        $column = $this->compiler->compile($node->column, $scope);
        $path = $node->path->value;
        $unquote = $node->unquote;
        $routine = new Routine($unquote ? '->>' : '->', 1, 1, static function (Frame $f, array $a) use ($path, $unquote): ?string {
            $document = Jsons::read($a[0], $f, 1, 'json_extract');
            if ($document === null) {
                return null;
            }
            try {
                $found = JsonPath::parse($path)->extract($document);
            } catch (JsonSyntax $failure) {
                throw DataError::InvalidJsonPath->error($failure->position);
            }
            if ($found === null) {
                return null;
            }

            return $unquote ? $found->unquoted() : $found->store();
        });

        return new Call($routine, [$column], $this->compiler->domain($node)->withSource($unquote ? '' : 'json_extract'));
    }

    /**
     * Compiles JSON_VALUE(): the path is read once, and a DEFAULT value is converted to the RETURNING type as CAST converts it.
     *
     * @throws SqlError When the path is not valid or an argument cannot be compiled
     */
    public function jsonValue(JsonValueCall $node, Scope $scope): Evaluable
    {
        $document = $this->compiler->compile($node->document, $scope);
        try {
            $path = JsonPath::parse($node->path->value());
        } catch (JsonSyntax $failure) {
            throw new SqlError(DataError::InvalidJsonPath, DataError::InvalidJsonPath->message($failure->position), $failure);
        }
        $domain = $this->compiler->domain($node);
        $response = function (?JsonResponse $response) use ($scope, $domain): array {
            $default = $response?->default;

            return [$response->kind ?? JsonResponseKind::Null, $default === null ? null : new Conversion($this->compiler->compile($default, $scope), $domain, null, 'CHAR')];
        };

        return new ValueCall($document, $path, $domain, $response($node->onEmpty), $response($node->onError));
    }

    /**
     * Compiles `value MEMBER OF (array)`.
     *
     * @throws SqlError When an operand cannot be compiled
     */
    public function member(MemberOf $node, Scope $scope): Evaluable
    {
        $value = $this->compiler->compile($node->operand, $scope);
        $array = $this->compiler->compile($node->array, $scope);
        $boolean = $this->boolean($node->operand);
        $routine = new Routine('MEMBER OF', 2, 2, static function (Frame $f, array $a) use ($boolean): ?int {
            $value = $a[0]->evaluate($f);
            if ($value === null) {
                return null;
            }
            $document = Jsons::read($a[1], $f, 2, 'member of');
            if ($document === null) {
                return null;
            }
            $member = Jsons::value($value, $a[0]->domain(), $boolean);
            foreach ($document->type === JsonKind::Array ? $document->children() : [$document] as $element) {
                if ($member->equals($element)) {
                    return 1;
                }
            }

            return 0;
        });

        return new Call($routine, [$value, $array], $this->compiler->domain($node));
    }

    /**
     * Tells whether an expression is a predicate, whose value becomes a JSON boolean.
     */
    public function boolean(Scalar $node): bool
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }

        return $node instanceof BooleanLiteral || $node instanceof Comparison || $node instanceof Logical || $node instanceof Not || $node instanceof NullTest || $node instanceof TruthTest
            || $node instanceof Between || $node instanceof InList || $node instanceof Like || $node instanceof Regexp || $node instanceof SoundsLike || $node instanceof MemberOf
            || $node instanceof Exists || $node instanceof InQuery || $node instanceof QuantifiedComparison;
    }

    /**
     * Reads a JSON argument of a function: a JSON value, or a string holding a JSON text; NULL is null.
     *
     * @param int $position The position of the argument, counted from 1
     * @param string $function The function name the server writes in its messages
     *
     * @throws SqlError When the argument is of another type, a binary string, or no JSON text
     */
    public static function document(int|float|string|null $value, Domain $domain, int $position, string $function): ?JsonNode
    {
        if ($domain->field === Field::Geometry || ($domain->kind !== Kind::String && $domain->kind !== Kind::Json && $domain->kind !== Kind::Null)) {
            throw DataError::InvalidJsonType->error($position, $function);
        }
        if ($value === null) {
            return null;
        }
        if ($domain->kind === Kind::Json) {
            return JsonNode::load((string) $value);
        }
        $text = (string) $value;
        if ($domain->kind === Kind::String) {
            $charset = $domain->collation->charset;
            if ($charset === Charset::binary()) {
                throw new SqlError(DataError::InvalidJsonCharset, DataError::InvalidJsonCharset->message('binary'), null, [[DataError::InvalidJsonType->value, DataError::InvalidJsonType->message($position, $function)]]);
            }
            $text = Encoding::convert($text, $charset, Charset::known('utf8mb4'));
        }

        return self::parse($text, $position, $function);
    }

    /**
     * Reads a JSON text given to a function.
     *
     * @param int $position The position of the argument, counted from 1
     * @param string $function The function name the server writes in its messages
     *
     * @throws SqlError When the text is no JSON document, or nests too deeply
     */
    public static function parse(string $text, int $position, string $function): JsonNode
    {
        try {
            return JsonNode::parse($text);
        } catch (JsonSyntax $failure) {
            $error = DataError::InvalidJsonTextInParameter->message($position, $function, $failure->reason, $failure->position);
            if ($failure->deep) {
                throw new SqlError(DataError::JsonDocumentTooDeep, DataError::JsonDocumentTooDeep->message(), $failure, [[DataError::InvalidJsonTextInParameter->value, $error]]);
            }

            throw new SqlError(DataError::InvalidJsonTextInParameter, $error, $failure);
        }
    }

    /**
     * Reads the JSON document an argument of a function holds for the row of a frame; NULL is null.
     * A non-document type is refused before evaluating the argument, including its casts and side effects.
     *
     * @param int $position The position of the argument, counted from 1
     * @param string $function The function name the server writes in its messages
     *
     * @throws SqlError When the argument is no JSON document
     */
    public static function read(Evaluable $argument, Frame $frame, int $position, string $function): ?JsonNode
    {
        if ($argument->domain()->field === Field::Geometry || !in_array($argument->domain()->kind, [Kind::String, Kind::Json, Kind::Null], true)) {
            throw DataError::InvalidJsonType->error($position, $function);
        }

        return self::document($argument->evaluate($frame), $argument->domain(), $position, $function);
    }

    /**
     * Reads the path an argument of a function holds for the row of a frame; NULL is null.
     *
     * A binary string is refused (ER_INVALID_JSON_CHARSET); any other value is read as its text.
     *
     * @throws SqlError When the path is not valid, at the position the server names
     */
    public static function path(Evaluable $argument, Frame $frame): ?JsonPath
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $argument->domain();
        $text = (string) Convert::toText($value, $domain);
        if ($domain->kind === Kind::String) {
            if ($domain->collation->charset === Charset::binary()) {
                throw DataError::InvalidJsonCharset->error('binary');
            }
            $text = Encoding::convert($text, $domain->collation->charset, Charset::known('utf8mb4'));
        }
        try {
            return JsonPath::parse($text);
        } catch (JsonSyntax $failure) {
            throw new SqlError(DataError::InvalidJsonPath, DataError::InvalidJsonPath->message($failure->position), $failure);
        }
    }

    /**
     * Makes the JSON value of an argument of a function that builds a document: NULL is the JSON null, a predicate a JSON boolean.
     *
     * @throws SqlError When the argument cannot be evaluated
     */
    public static function argument(Evaluable $argument, Frame $frame): JsonNode
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return new JsonNode(JsonKind::Null);
        }

        return self::value($value, $argument->domain(), $argument instanceof Predicate);
    }

    /**
     * Converts a value that is not NULL to JSON as CAST(value AS JSON) does, and answers it as an SQL expression holds a JSON value.
     *
     * A string is read as a JSON text, its errors naming the function cast_as_json, except the
     * value of an ENUM or SET, which is a JSON string; any other value is made a JSON value from
     * its type ({@see self::argument()}).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Converting between JSON and non-JSON values").
     *
     * @throws SqlError When a string is no JSON text
     */
    public static function cast(int|float|string $value, Evaluable $operand): string
    {
        $domain = $operand->domain();
        if ($domain->kind === Kind::String && $domain->collation->charset !== Charset::binary() && $domain->field !== Field::Enum && $domain->field !== Field::Set) {
            return self::parse(Encoding::convert((string) $value, $domain->collation->charset, Charset::known('utf8mb4')), 1, 'cast_as_json')->store();
        }

        return self::value($value, $domain, $operand instanceof Predicate)->store();
    }

    /**
     * Makes the JSON value of an SQL value that is not NULL.
     *
     * A number keeps its type, an unsigned integer and a YEAR being unsigned; a string is a JSON
     * string; a binary string and a BIT value are opaque values, written as `base64:typeN:` and
     * their bytes in base64, N being the type of the field; a temporal value keeps its type, a
     * datetime and a time written with six decimals; a JSON value is itself (verified on a live 8.4
     * server).
     *
     * @param bool $boolean Whether the value is that of a predicate
     */
    public static function value(int|float|string $value, Domain $domain, bool $boolean): JsonNode
    {
        if ($boolean) {
            return new JsonNode(JsonKind::Boolean, (int) $value !== 0);
        }
        $text = (string) Convert::toText($value, $domain);

        return match ($domain->kind) {
            Kind::Integer => new JsonNode($domain->unsigned ? JsonKind::Unsigned : JsonKind::Integer, $text),
            Kind::Year => new JsonNode(JsonKind::Unsigned, $text),
            Kind::Double => new JsonNode(JsonKind::Double, (float) $value),
            Kind::Decimal => new JsonNode(JsonKind::Decimal, $text),
            Kind::String => $domain->collation->charset === Charset::binary() ? self::opaque($text, self::stored($domain)) : new JsonNode(JsonKind::String, Encoding::convert($text, $domain->collation->charset, Charset::known('utf8mb4'))),
            Kind::Date => new JsonNode(JsonKind::Date, $text),
            Kind::Time => new JsonNode(JsonKind::Time, self::time($text)),
            Kind::DateTime => new JsonNode($domain->field === Field::Timestamp ? JsonKind::Timestamp : JsonKind::DateTime, self::dateTime($text)),
            Kind::Json => JsonNode::load((string) $value),
            Kind::Bit => self::opaque($text, Field::Bit),
            Kind::Null => new JsonNode(JsonKind::Null),
        };
    }

    /**
     * Answers the type the server stores a binary string of a domain as: a BLOB by its length, else its field.
     *
     * @example A TINYBLOB
     *     \MySqlMemory\Evaluation\Compile\Family\Jsons::stored(new \MySqlMemory\Typing\Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Blob, 255)) // => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::TinyBlob
     */
    public static function stored(Domain $domain): Field
    {
        if ($domain->field !== Field::Blob) {
            return $domain->field;
        }

        return match (true) {
            $domain->length <= 255 => Field::TinyBlob,
            $domain->length <= 65535 => Field::Blob,
            $domain->length <= 16777215 => Field::MediumBlob,
            default => Field::LongBlob,
        };
    }

    /**
     * Makes the opaque value of the bytes of a field: `base64:typeN:` and the bytes in base64 over lines of 76 characters, N the type the server stores the field as.
     *
     * @example The bytes of a VARBINARY value
     *     \MySqlMemory\Evaluation\Compile\Family\Jsons::opaque('ab', \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString)->text() // => '"base64:type15:YWI="'
     */
    public static function opaque(string $bytes, Field $field): JsonNode
    {
        $type = match ($field) {
            Field::VarString, Field::VarChar => 15,
            Field::Bit => 16,
            Field::Decimal, Field::Tiny, Field::Short, Field::Long, Field::Float, Field::Double, Field::Null, Field::Timestamp, Field::LongLong, Field::Int24, Field::Date, Field::Time, Field::DateTime, Field::Year, Field::NewDate,
            Field::Vector, Field::Json, Field::NewDecimal, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::String, Field::Geometry => $field->value,
        };

        return new JsonNode(JsonKind::Opaque, 'base64:type' . $type . ':' . rtrim(chunk_split(base64_encode($bytes), 76, "\n"), "\n"));
    }

    /**
     * Writes a datetime with six decimals, as a JSON value holds it.
     *
     * @example A datetime without decimals
     *     \MySqlMemory\Evaluation\Compile\Family\Jsons::dateTime('2024-01-31 10:00:00') // => '2024-01-31 10:00:00.000000'
     */
    public static function dateTime(string $text): string
    {
        $parts = Temporal::parseDateTime($text);

        return $parts === null ? $text : Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6], 6);
    }

    /**
     * Writes a time with six decimals, as a JSON value holds it.
     *
     * @example A time without decimals
     *     \MySqlMemory\Evaluation\Compile\Family\Jsons::time('-10:00:00') // => '-10:00:00.000000'
     */
    public static function time(string $text): string
    {
        $parts = Temporal::parseTime($text);

        return $parts === null ? $text : Temporal::time($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], 6);
    }
}
