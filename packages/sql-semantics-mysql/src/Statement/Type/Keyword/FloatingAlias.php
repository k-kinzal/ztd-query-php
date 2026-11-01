<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Keyword;

use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;

/**
 * The written synonyms of a floating type, separate from its execution kind.
 *
 * @visibility public
 * @example Reading the retained keyword
 *     \SqlSemantics\Platform\MySql\Statement\Type\Keyword\FloatingAlias::Float4->value // => 'FLOAT4'
 */
enum FloatingAlias: string
{
    case Float4 = 'FLOAT4';
    case Float8 = 'FLOAT8';
    case DoublePrecision = 'DOUBLE PRECISION';
    case Float8Precision = 'FLOAT8 PRECISION';

    /**
     * Answers the canonical type requested by this keyword alternative.
     */
    public function kind(): FloatingKind
    {
        return match ($this) {
            self::Float4 => FloatingKind::Float,
            self::Float8, self::DoublePrecision, self::Float8Precision => FloatingKind::Double,
        };
    }
}
