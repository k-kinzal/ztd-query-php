<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A fixed number of rows in a specified scan direction.
 * @visibility public
 * @example Reading a counted fetch
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('FETCH BACKWARD 2 IN cur');
 *     $statement->movement instanceof \SqlSemantics\Model\Cursor\CountedRows // => true
 *     $statement->movement->direction // => \SqlSemantics\Model\Cursor\ScanDirection::Backward
 *     $statement->movement->count->text // => '2'
 */
final class CountedRows implements Movement
{
    /**
     * A negative count reverses the scan; the number remains unevaluated here.
     */
    public function __construct(public readonly ScanDirection $direction, public readonly IntegerOffset $count)
    {
    }
}
