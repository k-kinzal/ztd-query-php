<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Xml;

/**
 * The explicit operands of an XMLTABLE namespacebinding declaration.
 * @visibility public
 * @example Reading a namespace declaration
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT x.* FROM XMLTABLE (XMLNAMESPACES ('urn:x' AS x), '/x:rows/x:row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY) AS x");
 *     [$statement->from->table->namespaces[0]->prefix, $statement->from->table->namespaces[0]->uri->spelling()] // => ['x', "'urn:x'"]
 */
final class NamespaceBinding
{
    /**
     * Retains semantic input expressions and declaration options.
     */
    public function __construct(public readonly \SqlSemantics\Model\Expression $uri, public readonly ?string $prefix = null)
    {
    }
}
