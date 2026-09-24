<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * A classified SQL/JSON wrapper policy.
 * @visibility public
 * @example Reading a wrapper policy
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER PATH '$.b' WITH WRAPPER)) AS j");
 *     $statement->from->table->columns[0]->wrapper // => \SqlSemantics\Model\TableFunction\Json\ArrayWrapping::Unconditional
 */
enum ArrayWrapping: string
{
    case Default = '';
    case Without = 'WITHOUT WRAPPER';
    case Conditional = 'WITH CONDITIONAL WRAPPER';
    case Unconditional = 'WITH UNCONDITIONAL WRAPPER';
}
