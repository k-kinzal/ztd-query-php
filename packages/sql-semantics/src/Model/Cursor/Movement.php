<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A request to position or advance a cursor; it does not fetch or evaluate rows.
 * @visibility public
 * @example Classifying cursor movements
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('FETCH FORWARD 3 FROM cur')->movement instanceof \SqlSemantics\Model\Cursor\Movement // => true
 *     $binder->bind('FETCH LAST FROM cur')->movement // => \SqlSemantics\Model\Cursor\RowPosition::Last
 *     $binder->bind('MOVE ABSOLUTE -2 FROM cur')->movement instanceof \SqlSemantics\Model\Cursor\PositionedRow // => true
 */
interface Movement
{
}
