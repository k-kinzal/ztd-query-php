<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * Every remaining row in the requested direction, without an artificial numeric count.
 * @visibility public
  * @example Inspecting RemainingRows
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('FETCH BACKWARD ALL FROM cur');
 *     $statement->movement instanceof \SqlSemantics\Model\Cursor\RemainingRows // => true
 */
final class RemainingRows implements Movement
{
    /**
     * Retains which end of the cursor the request advances toward.
     */
    public function __construct(public readonly ScanDirection $direction)
    {
    }
}
