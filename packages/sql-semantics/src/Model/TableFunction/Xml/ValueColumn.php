<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Xml;

/**
 * The explicit operands of an XMLTABLE valuecolumn declaration.
 * @visibility public
  * @example Inspecting ValueColumn
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind("SELECT x.* FROM XMLTABLE (XMLNAMESPACES ('urn:x' AS x), '/x:rows/x:row' PASSING BY REF '<rows/>' BY VALUE COLUMNS n FOR ORDINALITY, value INTEGER PATH '@id' DEFAULT 1 NOT NULL) AS x");
 *     $relation = $statement->from;
 *     $relation->table->columns[1] instanceof \SqlSemantics\Model\TableFunction\Xml\ValueColumn // => true
 */
final class ValueColumn
{
    /**
     * Retains semantic input expressions and declaration options.
     */
    public function __construct(public readonly string $name, public readonly \SqlSemantics\Type\TypeDescriptor $type, public readonly ?\SqlSemantics\Model\Expression $path = null, public readonly ?\SqlSemantics\Model\Expression $default = null, public readonly bool $notNull = false)
    {
    }
}
