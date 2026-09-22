<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Xml;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * XML row selection, namespace bindings, and typed per-row output declarations.
 * @visibility public
  * @example Inspecting XmlTable
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SELECT x.n, x.value FROM XMLTABLE ('/rows/row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY, value INTEGER PATH '@id') AS x");
 *     $query->relations[0]->table instanceof \SqlSemantics\Model\TableFunction\Xml\XmlTable // => true
 */
final class XmlTable
{
    /**
     * SQL language shared by the input document and every declaration.
     */
    public readonly \SqlSemantics\Dialect $dialect;

    /**
     * @var non-empty-list<Ordinality|ValueColumn> Output declarations
     */
    public readonly array $columns;

    /**
     * @param list<Ordinality|ValueColumn> $columns
     * @param list<NamespaceBinding> $namespaces
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly Expression $document,
        public readonly Expression $rowPath,
        array $columns,
        public readonly array $namespaces = [],
        public readonly PassingMode $inputMode = PassingMode::Default,
        public readonly PassingMode $outputMode = PassingMode::Default,
    ) {
        if ($columns === []) {
            throw new InvalidStructure('XMLTABLE requires an ordered nonempty output declaration list.');
        }
        Collections::alternatives($columns, [Ordinality::class, ValueColumn::class]);
        Collections::objects($namespaces, NamespaceBinding::class);
        $this->columns = $columns;
        $this->dialect = $document->type->dialect;
        \SqlSemantics\Model\TableFunction\DocumentInvariant::check($this);
    }
}
