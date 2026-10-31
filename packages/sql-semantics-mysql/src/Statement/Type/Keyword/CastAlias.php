<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Keyword;

use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

/**
 * The written synonyms of a cast type, separate from its execution kind.
 *
 * @visibility public
 * @example Reading the retained keyword
 *     \SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::Character->value // => 'CHARACTER'
 */
enum CastAlias: string
{
    case Character = 'CHARACTER';
    case NationalCharacter = 'NATIONAL CHARACTER';
    case SignedInteger = 'SIGNED INTEGER';
    case SignedInt4 = 'SIGNED INT4';
    case UnsignedInteger = 'UNSIGNED INTEGER';
    case UnsignedInt4 = 'UNSIGNED INT4';
    case Dec = 'DEC';
    case Float4 = 'FLOAT4';
    case Float8 = 'FLOAT8';
    case Float8Precision = 'FLOAT8 PRECISION';
    case GeomCollection = 'GEOMCOLLECTION';
    case SqlTsiYear = 'SQL_TSI_YEAR';

    /**
     * Answers the canonical type requested by this keyword alternative.
     */
    public function kind(): CastKind
    {
        return match ($this) {
            self::Character => CastKind::Char,
            self::NationalCharacter => CastKind::NationalChar,
            self::SignedInteger, self::SignedInt4 => CastKind::Signed,
            self::UnsignedInteger, self::UnsignedInt4 => CastKind::Unsigned,
            self::Dec => CastKind::Decimal,
            self::Float4 => CastKind::Float,
            self::Float8, self::Float8Precision => CastKind::Double,
            self::GeomCollection => CastKind::GeometryCollection,
            self::SqlTsiYear => CastKind::Year,
        };
    }

    /**
     * Answers whether this alternative includes the cast's optional words.
     */
    public function words(): \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords
    {
        return match ($this) {
            self::NationalCharacter, self::SignedInteger, self::SignedInt4, self::UnsignedInteger, self::UnsignedInt4, self::Float8Precision => \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written,
            self::Character, self::Dec, self::Float4, self::Float8, self::GeomCollection, self::SqlTsiYear => \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted,
        };
    }
}
