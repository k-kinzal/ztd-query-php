<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A collation, conversion, statistics object, or text search object addressed by a qualified name.
 * @visibility public
 * @example Addressing a text search dictionary
 *     $object = new \SqlSemantics\Model\Definition\Catalog\SchemaObjectIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\SchemaObjectKind::TextSearchDictionary, new \SqlSemantics\Model\Relation\QualifiedName(['english_stem']));
 *     $object->kind->value // => 'TEXT SEARCH DICTIONARY'
 */
final class SchemaObjectIdentity implements ObjectAddress
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Kind\SchemaObjectKind $kind, public readonly QualifiedName $name)
    {
        CatalogInvariant::name($name, 2);
    }
}
