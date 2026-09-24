<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json\Response;

/**
 * A classified SQL/JSON behavior policy.
 * @visibility public
 * @example Reading a value column response
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER PATH '$.b' NULL ON EMPTY)) AS j");
 *     $statement->from->table->columns[0]->onEmpty // => \SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior::Null
 */
enum ValueBehavior: string implements ValueResponse
{
    case Default = '';
    case Error = 'ERROR';
    case Null = 'NULL';
    case EmptyArray = 'EMPTY ARRAY';
    case EmptyObject = 'EMPTY OBJECT';
}
