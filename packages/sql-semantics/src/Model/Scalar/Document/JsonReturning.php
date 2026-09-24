<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document;

use SqlSemantics\Dialect;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * The RETURNING clause of a PostgreSQL SQL/JSON function: the declared result type and its optional JSON format.
 * An explicit encoding is only UTF8 and only for a bytea result.
 * @visibility public
 * @example Reading the returned type and its format
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT JSON_OBJECT('a': 1 RETURNING bytea FORMAT JSON ENCODING UTF8)");
 *     $returning = $query->outputs[0]->expression->returning;
 *     [$returning->type->name, $returning->format] // => ['bytea', \SqlSemantics\Model\TableFunction\Json\Format::Utf8]
 */
final class JsonReturning
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly TypeDescriptor $type, public readonly ?Format $format = null)
    {
        if ($type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A SQL/JSON RETURNING type is a PostgreSQL type.');
        }
        if (!self::accepts($type, $format)) {
            throw new InvalidStructure('A SQL/JSON RETURNING encoding is UTF8 for a bytea result.');
        }
    }

    /**
     * Whether PostgreSQL accepts the format for the returned type: an encoding requires bytea and only UTF8 is supported.
     */
    public static function accepts(TypeDescriptor $type, ?Format $format): bool
    {
        return $format === null || $format === Format::Json || $format === Format::Utf8 && $type->name === 'bytea';
    }
}
