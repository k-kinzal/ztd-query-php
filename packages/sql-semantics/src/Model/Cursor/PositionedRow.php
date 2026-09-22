<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A single row located relative to the start or the current cursor position.
 * @visibility public
  * @example Inspecting PositionedRow
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('MOVE ABSOLUTE -2 FROM cur');
 *     $statement->movement instanceof \SqlSemantics\Model\Cursor\PositionedRow // => true
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
