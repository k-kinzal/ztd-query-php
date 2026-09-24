<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Composite;

use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * One attribute of a composite type: its name, declared type, and optional collation.
 * @visibility public
 * @example Reading a composite attribute
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TYPE pair AS (label text COLLATE "C", amount integer)');
 *     $statement->attributes[0]->name // => 'label'
 *     $statement->attributes[0]->collation->parts // => ['C']
 *     $statement->attributes[1]->type->name // => 'integer'
 */
final class CompositeAttribute
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly TypeDescriptor $type, public readonly ?QualifiedName $collation = null)
    {
        TypeSystemInvariant::identifier($name);
        TypeSystemInvariant::type($type);
        if ($collation !== null) {
            TypeSystemInvariant::name($collation);
        }
    }
}
