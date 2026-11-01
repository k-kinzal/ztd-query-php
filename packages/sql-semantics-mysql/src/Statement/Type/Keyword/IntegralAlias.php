<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Keyword;

use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

/**
 * The written synonyms of a integral type, separate from its execution kind.
 *
 * @visibility public
 * @example Reading the retained keyword
 *     \SqlSemantics\Platform\MySql\Statement\Type\Keyword\IntegralAlias::Integer->value // => 'INTEGER'
 */
enum IntegralAlias: string
{
    case Integer = 'INTEGER';
    case Int1 = 'INT1';
    case Int2 = 'INT2';
    case Int3 = 'INT3';
    case Int4 = 'INT4';
    case Int8 = 'INT8';
    case MiddleInt = 'MIDDLEINT';

    /**
     * Answers the canonical type requested by this keyword alternative.
     */
    public function kind(): IntegralKind
    {
        return match ($this) {
            self::Integer, self::Int4 => IntegralKind::Int,
            self::Int1 => IntegralKind::TinyInt,
            self::Int2 => IntegralKind::SmallInt,
            self::Int3, self::MiddleInt => IntegralKind::MediumInt,
            self::Int8 => IntegralKind::BigInt,
        };
    }
}
