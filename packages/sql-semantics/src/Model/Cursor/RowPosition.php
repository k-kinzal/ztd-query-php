<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor rowposition choice.
 * @visibility public
 * @example Normalizing single-row fetches
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('FETCH LAST FROM cur')->movement // => \SqlSemantics\Model\Cursor\RowPosition::Last
 *     $binder->bind('FETCH BACKWARD FROM cur')->movement // => \SqlSemantics\Model\Cursor\RowPosition::Prior
 *     $binder->bind('FETCH FORWARD FROM cur')->toString() // => 'FETCH NEXT FROM "cur"'
 */
enum RowPosition: string implements Movement
{
    case Next = 'NEXT';
    case Prior = 'PRIOR';
    case First = 'FIRST';
    case Last = 'LAST';
}
