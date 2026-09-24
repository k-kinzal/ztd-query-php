<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document;
use SqlSemantics\Model\Scalar\Document\Construction;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Parts;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\TableFunction\Json;
use SqlSemantics\Serialization\Document\JsonTables;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Statements;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes PostgreSQL's SQL/JSON functions in their own syntax from their typed operands.
 * @visibility SqlSemantics
 */
final class SqlJsonExpressions
{
    /**
     * Leaves every other expression to the scalar serializer.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function write(Expression $value): ?Tree
    {
        return match (true) {
            $value instanceof Document\JsonQueryExtraction => self::call('JSON_QUERY', [self::document($value->document, $value->path, $value->passing), self::returning($value->returning), ...($value->wrapper === Json\ArrayWrapping::Default ? [] : [Build::keyword($value->wrapper->value)]), ...($value->quotes === Json\Quotes::Default ? [] : [Build::keyword($value->quotes->value)]), JsonTables::response($value->onEmpty, 'EMPTY'), JsonTables::response($value->onError, 'ERROR')]),
            $value instanceof Document\JsonExistence => self::call('JSON_EXISTS', [self::document($value->document, $value->path, $value->passing), ...($value->onError === Json\Response\ExistsResponse::Default ? [] : [Build::keyword($value->onError->value . ' ON ERROR')])]),
            $value instanceof Document\JsonSerialization => self::call('JSON_SERIALIZE', [JsonTables::input($value->input), self::returning($value->returning)]),
            $value instanceof Document\JsonParse => self::call('JSON', [JsonTables::input($value->input), ...($value->uniqueKeys ? [Build::keyword('WITH UNIQUE KEYS')] : [])]),
            $value instanceof Document\JsonScalarConversion => self::call('JSON_SCALAR', [Expressions::write($value->value)]),
            default => self::composite($value),
        };
    }

    /**
     * Writes the constructors and aggregates, leaving every other expression to the scalar serializer.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function composite(Expression $value): ?Tree
    {
        return match (true) {
            $value instanceof Construction\JsonObjectConstructor => self::call('JSON_OBJECT', [Build::separated(array_map(self::member(...), $value->members)), ...($value->members === [] ? [] : self::objectOptions($value->onNull, $value->uniqueKeys)), self::returning($value->returning)]),
            $value instanceof Construction\JsonArrayConstructor => self::call('JSON_ARRAY', [Build::separated(array_map(JsonTables::input(...), $value->elements)), ...($value->elements === [] || $value->onNull === Construction\JsonNullHandling::Absent ? [] : [Build::keyword($value->onNull->value)]), self::returning($value->returning)]),
            $value instanceof Construction\JsonArrayQuery => self::call('JSON_ARRAY', [Statements::write($value->query), ...($value->format === null ? [] : [Build::keyword($value->format->value)]), self::returning($value->returning)]),
            $value instanceof Construction\JsonObjectAggregate => self::filtered(self::call('JSON_OBJECTAGG', [self::member($value->member), ...self::objectOptions($value->onNull, $value->uniqueKeys), self::returning($value->returning)]), $value->filter),
            $value instanceof Construction\JsonArrayAggregate => self::filtered(self::call('JSON_ARRAYAGG', [JsonTables::input($value->element), Parts::ordering($value->orderBy), ...($value->onNull === Construction\JsonNullHandling::Absent ? [] : [Build::keyword($value->onNull->value)]), self::returning($value->returning)]), $value->filter),
            default => null,
        };
    }

    /**
     * Writes the keyword and its parenthesized clauses.
     * @param list<Tree> $parts
     */
    public static function call(string $keyword, array $parts): Tree
    {
        return new Tree('sql-json', [Build::keyword($keyword), Build::parentheses(new Tree('sql-json-arguments', $parts))]);
    }

    /**
     * Writes the formatted document, the path and the PASSING variables.
     * @param list<Json\PassingArgument> $passing
     */
    public static function document(Json\Input $document, Expression $path, array $passing): Tree
    {
        return new Tree('json-document', [JsonTables::input($document), new Atom('punctuation', ','), Expressions::write($path), self::passing($passing)]);
    }

    /**
     * Writes `PASSING value AS name, ...`, or nothing without variables.
     * @param list<Json\PassingArgument> $passing
     */
    public static function passing(array $passing): Tree
    {
        return new Tree('json-passing', $passing === [] ? [] : [Build::keyword('PASSING'), Build::separated(array_map(static fn (Json\PassingArgument $argument): Tree => new Tree('passing', [JsonTables::input($argument->input), Build::keyword('AS'), Build::identifier([$argument->name], Dialect::PostgreSql)]), $passing))]);
    }

    /**
     * Writes `RETURNING type [FORMAT JSON [ENCODING UTF8]]`, or nothing.
     */
    public static function returning(?Document\JsonReturning $returning): Tree
    {
        return new Tree('json-returning', $returning === null ? [] : [Build::keyword('RETURNING'), TypeDeclaration::write($returning->type), ...($returning->format === null ? [] : [Build::keyword($returning->format->value)])]);
    }

    /**
     * Writes a member as `key : value`.
     */
    public static function member(Construction\JsonMember $member): Tree
    {
        return new Tree('json-member', [Expressions::write($member->key), Build::keyword(':'), JsonTables::input($member->value)]);
    }

    /**
     * Writes ABSENT ON NULL and WITH UNIQUE KEYS when they differ from the object defaults.
     * @return list<Tree>
     */
    public static function objectOptions(Construction\JsonNullHandling $onNull, bool $uniqueKeys): array
    {
        return [...($onNull === Construction\JsonNullHandling::Null ? [] : [Build::keyword($onNull->value)]), ...($uniqueKeys ? [Build::keyword('WITH UNIQUE KEYS')] : [])];
    }

    /**
     * Appends a FILTER clause to an aggregate.
     */
    public static function filtered(Tree $call, ?Expression $filter): Tree
    {
        return $filter === null ? $call : new Tree('invocation', [$call, Build::keyword('FILTER'), Build::parentheses(new Tree('filter', [Build::keyword('WHERE'), Expressions::write($filter)]))]);
    }
}
