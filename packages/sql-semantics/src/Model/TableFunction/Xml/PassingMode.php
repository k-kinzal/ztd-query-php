<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Xml;

/**
 * The requested XML argument passing mode.
 * @visibility public
 * @example Reading the passing modes
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT x.* FROM XMLTABLE ('/rows/row' PASSING BY REF '<rows/>' BY VALUE COLUMNS n FOR ORDINALITY) AS x");
 *     [$statement->from->table->inputMode, $statement->from->table->outputMode] // => [\SqlSemantics\Model\TableFunction\Xml\PassingMode::Reference, \SqlSemantics\Model\TableFunction\Xml\PassingMode::Value]
 */
enum PassingMode: string
{
    case Default = '';
    case Reference = 'BY REF';
    case Value = 'BY VALUE';
}
