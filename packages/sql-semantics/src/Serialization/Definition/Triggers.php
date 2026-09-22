<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Trigger;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Insertions;
use SqlSemantics\Serialization\Mutations;
use SqlSemantics\Serialization\Query\Queries;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes trigger events and their native statements from the semantic structure.
 * @visibility SqlSemantics
 */
final class Triggers
{
    /**
     * Preserves each trigger step and the event's row predicate.
     */
    public static function writeSqlite(CreateSqliteTriggerStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        $event = $statement->event;
        $columns = $event instanceof Trigger\UpdatedColumns ? [Build::keyword('OF'), Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], $dialect), $event->columns))] : [];
        $steps = [];
        foreach ($statement->body->steps as $step) {
            $sql = match (true) {
                $step instanceof BoundQuery => Queries::write($step),
                $step instanceof InsertStatement => Insertions::write($step, true),
                default => Mutations::write($step, true),
            };
            array_push($steps, $sql, Build::keyword(';'));
        }
        return new Tree('trigger', [Build::keyword($statement->temporary ? 'CREATE TEMP TRIGGER' : 'CREATE TRIGGER'), ...($statement->ifNotExists ? [Build::keyword('IF NOT EXISTS')] : []), Build::identifier($statement->name->parts, $dialect), Build::keyword($statement->timing->value), Build::keyword($event->operation()->value), ...$columns, Build::keyword('ON'), Relations::target($statement->subject, $dialect), Build::keyword('FOR EACH ROW'), ...($statement->when === null ? [] : [Build::keyword('WHEN'), Expressions::write($statement->when)]), Build::keyword('BEGIN'), ...$steps, Build::keyword('END')]);
    }
}
