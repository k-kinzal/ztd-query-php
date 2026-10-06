<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A spatial type.
 *
 * `GEOMCOLLECTION` is `GEOMETRYCOLLECTION`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-type-overview.html.
 *
 * @visibility public
 * @example Reading a spatial type
 *     (new \SqlSemantics\Platform\MySql\Statement\Type\Spatial(\SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind::Point))->name() // => 'POINT'
 */
final class Spatial implements TypeName
{
    use Snapshot;

    /**
     * @param SpatialKind $kind The spatial type
     */
    public function __construct(public readonly SpatialKind $kind)
    {
    }

    /**
     * Names the type by its keyword.
     */
    public function name(): string
    {
        return $this->kind->value;
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value);
    }
}
