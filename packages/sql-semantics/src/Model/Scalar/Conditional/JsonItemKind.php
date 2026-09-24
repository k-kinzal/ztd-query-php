<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

/**
 * The kind of JSON item an IS JSON predicate requires; plain `IS JSON` means any value.
 * @visibility public
 * @example Reading the required JSON item type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SELECT '[1]' IS JSON ARRAY");
 *     $statement->outputs[0]->expression->itemKind // => \SqlSemantics\Model\Scalar\Conditional\JsonItemKind::JsonArray
 */
enum JsonItemKind: string
{
    case Value = 'VALUE';
    case JsonArray = 'ARRAY';
    case JsonObject = 'OBJECT';
    case Scalar = 'SCALAR';
}
