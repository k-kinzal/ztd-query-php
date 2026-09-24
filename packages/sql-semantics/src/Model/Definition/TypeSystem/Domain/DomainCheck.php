<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Domain;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A condition every value of the domain satisfies; the value under test is the column VALUE.
 * @visibility public
 * @example Reading a check condition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN positive AS integer CONSTRAINT above_zero CHECK (VALUE > 0)');
 *     $statement->constraints[0]->name // => 'above_zero'
 *     $statement->constraints[0]->condition instanceof \SqlSemantics\Model\Expression // => true
 */
final class DomainCheck implements DomainConstraint
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $condition, public readonly ?string $name = null)
    {
        if ($condition->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A domain check condition requires a PostgreSQL expression.');
        }
        if ($name !== null) {
            TypeSystemInvariant::identifier($name);
        }
    }
}
