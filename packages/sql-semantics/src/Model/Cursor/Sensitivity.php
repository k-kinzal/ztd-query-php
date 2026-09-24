<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor sensitivity choice.
 * @visibility public
 * @example Reading the declared sensitivity
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('DECLARE cur INSENSITIVE CURSOR FOR SELECT 1')->sensitivity // => \SqlSemantics\Model\Cursor\Sensitivity::Insensitive
 *     $binder->bind('DECLARE cur CURSOR FOR SELECT 1')->sensitivity // => \SqlSemantics\Model\Cursor\Sensitivity::Default
 */
enum Sensitivity: string
{
    case Default = '';
    case Asensitive = 'ASENSITIVE';
    case Insensitive = 'INSENSITIVE';
}
