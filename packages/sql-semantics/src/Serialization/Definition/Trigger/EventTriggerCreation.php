<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Trigger;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Trigger\DdlCommandTag;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateEventTriggerStatement;

/**
 * Writes CREATE EVENT TRIGGER from its event, tag filter, and function.
 * @visibility SqlSemantics
 */
final class EventTriggerCreation
{
    /**
     * Returns null for other statements.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof CreateEventTriggerStatement) {
            return null;
        }
        $tags = array_map(static fn (DdlCommandTag $tag): Tree => new Tree('literal', [new Atom('literal', Literal::encode($tag->value, Dialect::PostgreSql)[0])]), $statement->tags);
        return new Tree('create-event-trigger', [
            Build::keyword('CREATE EVENT TRIGGER'),
            Build::identifier([$statement->name], Dialect::PostgreSql),
            Build::keyword('ON'),
            Build::identifier([$statement->event->value], Dialect::PostgreSql),
            ...($tags === [] ? [] : [Build::keyword('WHEN TAG IN'), Build::parentheses(Build::separated($tags))]),
            Build::keyword('EXECUTE FUNCTION'),
            Build::identifier($statement->function->parts, Dialect::PostgreSql),
            new Tree('arguments', [new Atom('punctuation', '('), new Atom('punctuation', ')')]),
        ]);
    }
}
