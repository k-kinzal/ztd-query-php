<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Domain;

use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rejects NULL as a value of the domain, optionally under a constraint name.
 * @visibility public
 * @example Reading a named NOT NULL constraint
 *     $constraint = new \SqlSemantics\Model\Definition\TypeSystem\Domain\DomainNotNull('present');
 *     $constraint->name // => 'present'
 */
final class DomainNotNull implements DomainConstraint
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
