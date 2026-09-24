<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * @visibility public
 * @example Reading the connection release of a commit
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build());
 *     $binder->bind('COMMIT RELEASE')->release // => \SqlSemantics\Model\Transaction\Release::Release
 *     $binder->bind('COMMIT AND CHAIN NO RELEASE')->release // => \SqlSemantics\Model\Transaction\Release::NoRelease

 */
enum Release: string
{
    case Default = 'default';
    case Release = 'release';
    case NoRelease = 'no-release';
}
