<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Trigger;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;

/**
 * Routes PostgreSQL triggers, rules, policies, and logical replication objects to their writers.
 * @visibility SqlSemantics
 */
final class ChangeReactions
{
    /**
     * Returns null for statements outside these object families.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return EventTriggerCommands::write($statement)
            ?? RelationTriggers::write($statement)
            ?? EventTriggerCreation::write($statement)
            ?? Policies::write($statement)
            ?? Rules::write($statement)
            ?? \SqlSemantics\Serialization\Definition\Replication\Publications::write($statement)
            ?? \SqlSemantics\Serialization\Definition\Replication\Subscriptions::write($statement);
    }
}
