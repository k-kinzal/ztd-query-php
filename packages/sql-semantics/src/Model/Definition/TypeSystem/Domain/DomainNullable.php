<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Domain;

use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Declares explicitly that the domain admits NULL, the default that a NULL clause restates.
 * @visibility public
 * @example Reading an explicit NULL constraint
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN note AS text NULL');
 *     $statement->constraints[0] instanceof \SqlSemantics\Model\Definition\TypeSystem\Domain\DomainNullable // => true
 */
final class DomainNullable implements DomainConstraint
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $name = null)
    {
        if ($name !== null) {
            TypeSystemInvariant::identifier($name);
        }
    }
}
