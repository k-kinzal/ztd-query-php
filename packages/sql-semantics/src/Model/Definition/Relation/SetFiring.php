<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Definition\Trigger\TriggerFiring;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Enables or disables a trigger or rule of a table in the selected replication contexts.
 * Trigger groups accept only plain enabling and disabling.
 * @visibility public
 * @example Enabling a trigger for every replication role
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ENABLE ALWAYS TRIGGER audit');
 *     $statement->actions[0]->firing // => \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Always
 *     $statement->actions[0]->name // => 'audit'
 * @example Rejecting a replica-only policy for a trigger group
 *     new \SqlSemantics\Model\Definition\Relation\SetFiring(\SqlSemantics\Model\Definition\Relation\FiringTarget::Trigger, \SqlSemantics\Model\Definition\Relation\TriggerGroup::All, \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Replica); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetFiring implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly FiringTarget $target, public readonly string|TriggerGroup $name, public readonly TriggerFiring $firing)
    {
        if (is_string($name)) {
            CatalogInvariant::identifier($name);
        } elseif ($target === FiringTarget::Rule || !in_array($firing, [TriggerFiring::Origin, TriggerFiring::Disabled], true)) {
            throw new InvalidStructure('Trigger groups accept only plain enabling or disabling.');
        }
    }
}
