<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Keyword;

use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;

/**
 * The written synonyms of a spatial type, separate from its execution kind.
 *
 * @visibility public
 * @example Reading the retained keyword
 *     \SqlSemantics\Platform\MySql\Statement\Type\Keyword\SpatialAlias::GeomCollection->value // => 'GEOMCOLLECTION'
 */
enum SpatialAlias: string
{
    case GeomCollection = 'GEOMCOLLECTION';

    /**
     * Answers the canonical type requested by this keyword alternative.
     */
    public function kind(): SpatialKind
    {
        return match ($this) {
            self::GeomCollection => SpatialKind::GeometryCollection,
        };
    }
}
