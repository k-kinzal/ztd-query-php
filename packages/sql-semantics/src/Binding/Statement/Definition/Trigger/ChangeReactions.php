<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Trigger;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes the PostgreSQL objects that react to or govern changes: relation and event triggers, rules, policies, and logical replication.
 * @visibility SqlSemantics
 */
final class ChangeReactions
{
    /**
     * Returns null for statements outside these object families.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            return null;
        }
        return EventTriggerBinder::bind($origin, $statement, $context) ?? match ($statement->name) {
            'CreateTrigStmt' => RelationTriggers::bind($origin, $statement, $context),
            'CreateEventTrigStmt' => EventTriggerCreation::bind($origin, $statement, $context),
            'CreatePolicyStmt', 'AlterPolicyStmt' => Policies::bind($origin, $statement, $context),
            'RuleStmt' => Rules::bind($origin, $statement, $context),
            'CreatePublicationStmt', 'AlterPublicationStmt' => \SqlSemantics\Binding\Statement\Definition\Replication\Publications::bind($origin, $statement, $context),
            'CreateSubscriptionStmt', 'AlterSubscriptionStmt', 'DropSubscriptionStmt' => \SqlSemantics\Binding\Statement\Definition\Replication\Subscriptions::bind($origin, $statement, $context),
            default => null,
        };
    }
}
