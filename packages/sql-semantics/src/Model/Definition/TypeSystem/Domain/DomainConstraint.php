<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Domain;

/**
 * A constraint a PostgreSQL domain places on its values: NOT NULL, an explicit NULL, or a CHECK condition.
 * @visibility public
 * @example Reading the constraints of a domain
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN positive AS integer NOT NULL CHECK (VALUE > 0)');
 *     $statement->constraints[0] instanceof \SqlSemantics\Model\Definition\TypeSystem\Domain\DomainConstraint // => true
 *     $statement->constraints[1] instanceof \SqlSemantics\Model\Definition\TypeSystem\Domain\DomainCheck // => true
 */
interface DomainConstraint
{
}
