<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL array with a mandatory element type and declared dimensions.
 * @visibility public
 */
final class ArrayStorage implements TypeIdentity
{
    /**
     * @var non-empty-list<ArrayDimension> Validated ordered operands
     */
    public readonly array $dimensions;

    /**
     * @param list<ArrayDimension> $dimensions
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly \SqlSemantics\Type\TypeDescriptor $element,
        array $dimensions,
    ) {
        Collections::objects($dimensions, ArrayDimension::class);
        if ($dimensions === [] || $element->dialect !== \SqlSemantics\Dialect::PostgreSql) {
            throw new InvalidStructure('An array requires a PostgreSQL element type and at least one dimension.');
        }
        $this->dimensions = Collections::nonEmpty($dimensions);
    }

    /**
     * Returns the canonical database type name represented by this identity.
     */
    #[Override]
    public function name(): string
    {
        return $this->element->name . '[]';
    }
}
