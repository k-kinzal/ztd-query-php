<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

use SqlSemantics\Model\Definition\RelationAction;

/**
 * Enables, disables, forces, or stops forcing row-level security on the table.
 * @visibility public
 * @example Forcing policies for the table owner
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t FORCE ROW LEVEL SECURITY');
 *     $statement->actions[0]->change // => \SqlSemantics\Model\Definition\Relation\RowSecurityChange::Force
 */
final class SetRowSecurity implements RelationAction
{
    /**
     * The switch is the complete operand.
     */
    public function __construct(public readonly RowSecurityChange $change)
    {
    }
}
