<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Rendering\Output;

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
final class Spatial implements DeclaredName
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param SpatialKind $kind The spatial type
     * @param Keyword\SpatialAlias|null $alias The written synonym; null uses the canonical keyword form
     */
    public function __construct(public readonly SpatialKind $kind, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?Keyword\SpatialAlias $alias = null)
    {
        Check::input($alias === null || $alias->kind() === $kind, 'A type synonym must match its canonical type role.');
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
        $out->keyword(...explode(' ', $this->alias->value ?? $this->kind->value));
    }
}
