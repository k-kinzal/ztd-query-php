<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Definition;

/**
 * An attribute of a range type definition.
 * @visibility public
 * @example Reading the argument form of the subtype
 *     \SqlSemantics\Model\Definition\TypeSystem\Definition\RangeAttribute::Subtype->kind() // => \SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind::Type
 */
enum RangeAttribute: string implements DefinitionAttribute
{
    case Subtype = 'SUBTYPE';
    case SubtypeOpclass = 'SUBTYPE_OPCLASS';
    case Collation = 'COLLATION';
    case Canonical = 'CANONICAL';
    case SubtypeDiff = 'SUBTYPE_DIFF';
    case MultirangeTypeName = 'MULTIRANGE_TYPE_NAME';

    /**
     * The attribute name as written in SQL.
     */
    public function spelling(): string
    {
        return $this->value;
    }

    /**
     * The subtype is a type; every other attribute names a catalog object.
     */
    public function kind(): DefinitionKind
    {
        return $this === self::Subtype ? DefinitionKind::Type : DefinitionKind::Name;
    }

    /**
     * Range attributes have no keyword choices.
     */
    public function choose(string $text): ?string
    {
        return null;
    }
}
