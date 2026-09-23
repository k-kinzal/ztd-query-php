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
 * @example Inspecting declared precision inputs
 *     $type = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(x numeric("12"))')->tables[0]->columns[0]->type;
 *     $type->identity->precision->name // => '12'
 */
final class NumericStorage implements TypeIdentity
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly BuiltinIdentity $base,
        public readonly NumericParameter|\SqlSemantics\Type\Modifier\TextParameter|\SqlSemantics\Type\Modifier\IdentifierParameter|\SqlSemantics\Type\Modifier\NegatedParameter|null $precision = null,
        public readonly NumericParameter|\SqlSemantics\Type\Modifier\TextParameter|\SqlSemantics\Type\Modifier\IdentifierParameter|\SqlSemantics\Type\Modifier\NegatedParameter|null $scale = null,
        public readonly bool $unsigned = false,
    ) {
        if ($base !== BuiltinIdentity::Numeric && (($precision !== null && !$precision instanceof NumericParameter) || ($scale !== null && !$scale instanceof NumericParameter))) {
            throw new InvalidStructure('Floating-point parameter syntax requires numeric literals.');
        }
        if (!in_array($base, [BuiltinIdentity::Numeric, BuiltinIdentity::Real, BuiltinIdentity::Float, BuiltinIdentity::DoublePrecision], true) || ($scale !== null && $precision === null)) {
            throw new InvalidStructure('Numeric scale requires precision and a numeric storage family.');
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
