<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A single row located relative to the start or the current cursor position.
 * @visibility public
 */
final class PositionedRow implements Movement
{
    /**
     * Requires an offset whenever an absolute or relative position is requested.
     */
    public function __construct(public readonly OffsetOrigin $origin, public readonly IntegerOffset $offset)
    {
    }
}
