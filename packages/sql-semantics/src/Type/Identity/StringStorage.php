<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A character, binary or bit string type with its declared length and encoding.
 * @visibility public
 * @example Inspecting a bit-string length operand
 *     $type = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(x bit("12"))')->tables[0]->columns[0]->type;
 *     $type->identity->length->name // => '12'
 */
final class StringStorage implements TypeIdentity
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly BuiltinIdentity $base,
        public readonly Numeric\NumericParameter|\SqlSemantics\Type\Modifier\TextParameter|\SqlSemantics\Type\Modifier\IdentifierParameter|\SqlSemantics\Type\Modifier\NegatedParameter|null $length = null,
        public readonly ?string $characterSet = null,
        public readonly bool $binary = false,
        public readonly bool $national = false,
    ) {
        if ($national && ($characterSet !== null || !in_array($base, [BuiltinIdentity::Char, BuiltinIdentity::Varchar], true))) {
            throw new InvalidStructure('A national character type requires CHAR or VARCHAR and owns its character-set selection.');
        }
        if ($length !== null && !$length instanceof Numeric\NumericParameter && !in_array($base, [BuiltinIdentity::Bit, BuiltinIdentity::Varbit], true)) {
            throw new InvalidStructure('Only PostgreSQL bit-string syntax accepts nonnumeric length operands.');
        }
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
