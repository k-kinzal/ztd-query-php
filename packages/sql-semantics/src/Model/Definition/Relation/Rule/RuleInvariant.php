<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Rule;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\Notification\NotifyStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The rule shapes PostgreSQL's rewrite system accepts.
 * @visibility SqlSemantics
 */
final class RuleInvariant
{
    /**
     * A rule is a PostgreSQL object with a nonempty name and a PostgreSQL condition.
     * @throws InvalidStructure
     */
    public static function identity(Origin $origin, string $name, ?Expression $condition): void
    {
        if ($origin->dialect !== Dialect::PostgreSql || $name === '' || ($condition !== null && $condition->type->dialect !== Dialect::PostgreSql)) {
            throw new InvalidStructure('A rule requires PostgreSQL, a nonempty name, and a PostgreSQL condition.');
        }
    }

    /**
     * Rule actions are queries, INSERT, UPDATE, DELETE, or NOTIFY; NOTIFY needs an unconditional rule, RETURNING an unconditional INSTEAD rule with one such action, and an ON SELECT rule is the view's replaced _RETURN rule doing INSTEAD one query.
     * @param list<BoundQuery|InsertStatement|UpdateStatement|DeleteStatement|NotifyStatement> $actions
     * @return non-empty-list<BoundQuery|InsertStatement|UpdateStatement|DeleteStatement|NotifyStatement>
     * @throws InvalidStructure
     */
    public static function actions(string $name, RuleEvent $event, array $actions, bool $instead, ?Expression $condition, bool $orReplace): array
    {
        Collections::alternatives($actions, [BoundQuery::class, InsertStatement::class, UpdateStatement::class, DeleteStatement::class, NotifyStatement::class]);
        $returning = 0;
        foreach ($actions as $action) {
            self::dialect($action);
            if ($action instanceof NotifyStatement && $condition !== null) {
                throw new InvalidStructure('A conditional rule cannot NOTIFY.');
            }
            $returning += !$action instanceof BoundQuery && !$action instanceof NotifyStatement && $action->outputs !== [] ? 1 : 0;
        }
        if ($returning > 1 || ($returning === 1 && (!$instead || $condition !== null))) {
            throw new InvalidStructure('RETURNING needs an unconditional INSTEAD rule with one returning action.');
        }
        if ($event === RuleEvent::Select && (count($actions) !== 1 || !$actions[0] instanceof BoundQuery || !$instead || $condition !== null || $name !== '_RETURN' || !$orReplace)) {
            throw new InvalidStructure('A rule on SELECT replaces a view\'s _RETURN rule and does INSTEAD exactly one query without a condition.');
        }
        return Collections::nonEmpty($actions);
    }

    /**
     * A nested action must use the rule's dialect.
     * @throws InvalidStructure
     */
    public static function dialect(BoundStatement $action): void
    {
        if ($action->origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A rule action must use the PostgreSQL dialect.');
        }
    }

    /**
     * The row images a rule can read: OLD for UPDATE and DELETE, NEW for INSERT and UPDATE.
     * @return list<RowVersion>
     */
    public static function images(RuleEvent $event): array
    {
        return match ($event) {
            RuleEvent::Select => [],
            RuleEvent::Insert => [RowVersion::New],
            RuleEvent::Update => [RowVersion::Old, RowVersion::New],
            RuleEvent::Delete => [RowVersion::Old],
        };
    }
}
