<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor scrollability choice.
 * @visibility public
 * @example Reading the declared scroll policy
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('DECLARE cur NO SCROLL CURSOR FOR SELECT 1')->scroll // => \SqlSemantics\Model\Cursor\Scrollability::ForwardOnly
 *     $binder->bind('DECLARE cur CURSOR FOR SELECT 1')->scroll // => \SqlSemantics\Model\Cursor\Scrollability::Default
 */
enum Scrollability: string
{
    case Default = '';
    case Scroll = 'SCROLL';
    case ForwardOnly = 'NO SCROLL';
}
