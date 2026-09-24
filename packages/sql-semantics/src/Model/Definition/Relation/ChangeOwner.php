<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\RelationAction;

/**
 * Transfers ownership of the relation to a named or session role.
 * @visibility public
 * @example Reading the new owner
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t OWNER TO CURRENT_ROLE');
 *     $statement->actions[0]->newOwner // => \SqlSemantics\Model\Configuration\Role\SessionRole::CurrentRole
 */
final class ChangeOwner implements RelationAction
{
    /**
     * The role is the complete operand.
     */
    public function __construct(public readonly NamedRole|SessionRole $newOwner)
    {
    }
}
