<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Xml;

/**
 * The explicit operands of an XMLTABLE ordinality declaration.
 * @visibility public
 * @example Reading an XMLTABLE ordinal column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT x.* FROM XMLTABLE ('/rows/row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY) AS x");
 *     $statement->from->table->columns[0]->name // => 'n'
 */
final class Ordinality
{
    /**
     * Retains semantic input expressions and declaration options.
     */
    public function __construct(public readonly string $name)
    {
    }
}
