<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A constraint addressed by its name and the domain that declares it.
 * @visibility public
 * @example Addressing a domain constraint
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COMMENT ON CONSTRAINT positive ON DOMAIN app.money IS 'x'");
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\DomainConstraintIdentity // => true
 *     $statement->object->domain->parts // => ['app', 'money']
 */
final class DomainConstraintIdentity implements ObjectAddress
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly QualifiedName $domain)
    {
        CatalogInvariant::identifier($name);
        CatalogInvariant::name($domain, 2);
    }
}
