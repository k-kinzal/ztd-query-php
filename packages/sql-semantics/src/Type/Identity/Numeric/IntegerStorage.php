<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity\Numeric;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\TypeIdentity;

/**
 * An integer storage type with its signedness and optional display width.
 * @visibility public
 */
final class IntegerStorage implements TypeIdentity
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly BuiltinIdentity $base,
        public readonly ?NumericParameter $displayWidth = null,
        public readonly bool $unsigned = false,
    ) {
        if (!in_array($base, [BuiltinIdentity::TinyInt, BuiltinIdentity::SmallInt, BuiltinIdentity::MediumInt, BuiltinIdentity::Integer, BuiltinIdentity::BigInt, BuiltinIdentity::Year], true)) {
            throw new InvalidStructure('An integer type requires an integer storage family.');
        }
    }

    /**
     * Returns the canonical database type name represented by this identity.
     */
    #[Override]
    public function name(): string
    {
        return $this->base->value . ($this->unsigned ? ' unsigned' : '');
    }
}
