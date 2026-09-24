<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor offsetorigin choice.
 * @visibility public
 * @example Reading the origin of a positioned fetch
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('FETCH ABSOLUTE 3 FROM cur')->movement->origin // => \SqlSemantics\Model\Cursor\OffsetOrigin::Absolute
 *     $binder->bind('FETCH RELATIVE -1 FROM cur')->movement->origin // => \SqlSemantics\Model\Cursor\OffsetOrigin::Relative
 */
enum OffsetOrigin: string
{
    case Absolute = 'ABSOLUTE';
    case Relative = 'RELATIVE';
}
