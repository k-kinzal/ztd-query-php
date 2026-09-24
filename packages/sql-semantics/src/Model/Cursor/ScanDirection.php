<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor scandirection choice.
 * @visibility public
 * @example Reading the direction of a scan
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('FETCH BACKWARD ALL FROM cur')->movement->direction // => \SqlSemantics\Model\Cursor\ScanDirection::Backward
 *     $binder->bind('FETCH -5 FROM cur')->movement->direction // => \SqlSemantics\Model\Cursor\ScanDirection::Forward
 */
enum ScanDirection: string
{
    case Forward = 'FORWARD';
    case Backward = 'BACKWARD';
}
