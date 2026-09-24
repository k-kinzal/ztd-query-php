<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Constraint;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\IndexElement;

/**
 * One indexed value of an exclusion constraint and the operator two rows must not satisfy.
 * @visibility public
 * @example Reading the operator
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD EXCLUDE USING gist (id WITH =)');
 *     $statement->actions[0]->constraint->elements[0]->operator->parts // => ['=']
 */
final class ExclusionElement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly IndexElement $key, public readonly QualifiedName $operator)
    {
        CatalogInvariant::name($operator, 2);
    }
}
