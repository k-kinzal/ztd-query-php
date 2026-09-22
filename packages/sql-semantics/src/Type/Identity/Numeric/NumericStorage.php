<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity\Numeric;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\TypeIdentity;

/**
 * A decimal or floating type with numeric precision and scale.
 * @visibility public
 */
final class NumericStorage implements TypeIdentity
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly BuiltinIdentity $base,
        public readonly ?NumericParameter $precision = null,
        public readonly ?NumericParameter $scale = null,
        public readonly bool $unsigned = false,
    ) {
        if (!in_array($base, [BuiltinIdentity::Numeric, BuiltinIdentity::Real, BuiltinIdentity::Float, BuiltinIdentity::DoublePrecision], true) || ($scale !== null && $precision === null)) {
            throw new InvalidStructure('Numeric scale requires precision and a numeric storage family.');
        }
    }

    #[Override]
    public function name(): string
    {
        return $this->base->value . ($this->unsigned ? ' unsigned' : '');
    }
}
