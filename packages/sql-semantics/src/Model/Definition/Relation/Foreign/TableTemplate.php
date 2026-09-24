<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Foreign;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A LIKE clause copying column definitions from a template table into a declaration.
 * @visibility public
 * @example Reading the template and its selections
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN TABLE ft (LIKE app.t INCLUDING DEFAULTS EXCLUDING COMMENTS) SERVER s');
 *     $statement->templates[0]->source->parts // => ['app', 't']
 *     $statement->templates[0]->selections[1]->property // => \SqlSemantics\Model\Definition\Relation\Foreign\TemplateProperty::Comments
 */
final class TableTemplate
{
    /**
     * @param list<TemplateSelection> $selections
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $source, public readonly array $selections = [])
    {
        CatalogInvariant::name($source, 3);
        Collections::objects($selections, TemplateSelection::class);
    }
}
