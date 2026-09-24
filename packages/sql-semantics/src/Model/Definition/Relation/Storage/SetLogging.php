<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

use SqlSemantics\Model\Definition\RelationAction;

/**
 * Switches the relation between logged and unlogged persistence.
 * @visibility public
 * @example Making a sequence unlogged
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s SET UNLOGGED');
 *     $statement->actions[0]->logging // => \SqlSemantics\Model\Definition\Relation\Storage\RelationLogging::Unlogged
 */
final class SetLogging implements RelationAction
{
    /**
     * The persistence is the complete operand.
     */
    public function __construct(public readonly RelationLogging $logging)
    {
    }
}
