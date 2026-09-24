<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Pragma;

/**
 * A classified pragma argument.
 *
 * @visibility public
 * @example Reading the bare identifier of a pragma assignment
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build());
 *     $statement = $binder->bind('PRAGMA journal_mode=wal');
 *     $statement->value instanceof \SqlSemantics\Model\Configuration\Pragma\IdentifierArgument // => true
 *     $statement->value->name // => 'wal'
 */
final class IdentifierArgument implements Argument
{
    /**

     */
    public function __construct(public readonly string $name)
    {
    }
}
