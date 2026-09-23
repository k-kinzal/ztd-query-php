<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL type referenced by name, with classified type-input operands.
 * @visibility public
 * @example Reading classified named-type inputs
 *     $type = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(x app.measure(currency))')->tables[0]->columns[0]->type;
 *     $type->identity->arguments[0]->name // => 'currency'
 * @example Rejecting row expressions in the modifier domain
 *     new \SqlSemantics\Type\Identity\NamedIdentity(new \SqlSemantics\Model\Relation\QualifiedName(['measure']), [\SqlSemantics\Model\Expression::literal(1, \SqlSemantics\Dialect::PostgreSql)]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class NamedIdentity implements TypeIdentity
{
    /**
     * @param list<Numeric\NumericParameter|\SqlSemantics\Type\Modifier\TextParameter|\SqlSemantics\Type\Modifier\IdentifierParameter|\SqlSemantics\Type\Modifier\NegatedParameter> $arguments
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly \SqlSemantics\Model\Relation\QualifiedName $reference,
        public readonly array $arguments = [],
    ) {
        Collections::alternatives($arguments, [Numeric\NumericParameter::class, \SqlSemantics\Type\Modifier\TextParameter::class, \SqlSemantics\Type\Modifier\IdentifierParameter::class, \SqlSemantics\Type\Modifier\NegatedParameter::class]);
    }

    /**
     * Returns the canonical database type name represented by this identity.
     */
    #[Override]
    public function name(): string
    {
        return implode('.', $this->reference->parts);
    }
}
