<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Pragma;

/**
 * A pragma's scalar argument; general SQL expressions are not accepted here.
 *
 * @visibility public
 * @example Classifying a pragma argument
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build());
 *     $statement = $binder->bind('PRAGMA journal_mode=wal');
 *     $statement->value instanceof \SqlSemantics\Model\Configuration\Pragma\Argument // => true
 *     $statement->value instanceof \SqlSemantics\Model\Configuration\Pragma\IdentifierArgument // => true
 */
interface Argument
{
}
