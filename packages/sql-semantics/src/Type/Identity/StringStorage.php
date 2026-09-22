<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A character, binary or bit string type with its declared length and encoding.
 * @visibility public
 */
final class StringStorage implements TypeIdentity
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly BuiltinIdentity $base,
        public readonly ?Numeric\NumericParameter $length = null,
        public readonly ?string $characterSet = null,
        public readonly bool $binary = false,
    ) {
        if (!in_array($base, [BuiltinIdentity::Char, BuiltinIdentity::Varchar, BuiltinIdentity::Text, BuiltinIdentity::TinyText, BuiltinIdentity::MediumText, BuiltinIdentity::LongText, BuiltinIdentity::Binary, BuiltinIdentity::Varbinary, BuiltinIdentity::Blob, BuiltinIdentity::TinyBlob, BuiltinIdentity::MediumBlob, BuiltinIdentity::LongBlob, BuiltinIdentity::Bit, BuiltinIdentity::Varbit, BuiltinIdentity::Vector], true)) {
            throw new InvalidStructure('A string type requires a character, binary or bit storage family.');
        }
    }

    /**
     * Returns the canonical database type name represented by this identity.
     */
    #[Override]
    public function name(): string
    {
        return $this->base->value;
    }
}
