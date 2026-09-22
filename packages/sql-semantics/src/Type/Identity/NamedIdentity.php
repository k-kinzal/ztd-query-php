<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL type referenced by name, with structured modifier expressions.
 * @visibility public
 */
final class NamedIdentity implements TypeIdentity
{
    /**
     * @param list<\SqlSemantics\Model\Expression> $arguments
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly \SqlSemantics\Model\Relation\QualifiedName $reference,
        public readonly array $arguments = [],
    ) {
        Collections::objects($arguments, \SqlSemantics\Model\Expression::class);
    }

    #[Override]
    public function name(): string
    {
        return implode('.', $this->reference->parts);
    }
}
