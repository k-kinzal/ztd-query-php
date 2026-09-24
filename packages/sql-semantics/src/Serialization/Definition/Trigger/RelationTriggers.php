<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Trigger;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Trigger as Operand;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger as Statement;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes relation and constraint triggers from their typed events, granularity, and invocation.
 * @visibility SqlSemantics
 */
final class RelationTriggers
{
    /**
     * Returns null for other statements.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof Statement\CreateTriggerStatement) {
            return new Tree('create-trigger', [
                Build::keyword($statement->orReplace ? 'CREATE OR REPLACE TRIGGER' : 'CREATE TRIGGER'),
                Build::identifier([$statement->name], Dialect::PostgreSql),
                Build::keyword($statement->timing->value),
                self::events($statement->events),
                Build::keyword('ON'),
                Relations::target($statement->table, Dialect::PostgreSql),
                ...self::transitions($statement->transitions),
                Build::keyword('FOR EACH ' . $statement->level->value),
                ...self::condition($statement->condition),
                self::invocation($statement->invocation),
            ]);
        }
        if (!$statement instanceof Statement\CreateConstraintTriggerStatement) {
            return null;
        }
        return new Tree('create-constraint-trigger', [
            Build::keyword('CREATE CONSTRAINT TRIGGER'),
            Build::identifier([$statement->name], Dialect::PostgreSql),
            Build::keyword('AFTER'),
            self::events($statement->events),
            Build::keyword('ON'),
            Relations::target($statement->table, Dialect::PostgreSql),
            ...($statement->referenced === null ? [] : [Build::keyword('FROM'), Relations::target($statement->referenced, Dialect::PostgreSql)]),
            ...match ($statement->checking) {
                CheckingTime::Immediate => [],
                CheckingTime::DeferrableImmediate => [Build::keyword('DEFERRABLE')],
                CheckingTime::DeferrableDeferred => [Build::keyword('DEFERRABLE INITIALLY DEFERRED')],
            },
            Build::keyword('FOR EACH ROW'),
            ...self::condition($statement->condition),
            self::invocation($statement->invocation),
        ]);
    }

    /**
     * Joins the events with OR and attaches the UPDATE column list to UPDATE.
     */
    public static function events(Operand\TriggerEvents $events): Tree
    {
        $columns = Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], Dialect::PostgreSql), $events->columns));
        $parts = [];
        foreach ($events->events as $event) {
            if ($parts !== []) {
                $parts[] = Build::keyword('OR');
            }
            $parts[] = $event === Operand\TriggerEvent::Update && $events->columns !== [] ? new Tree('update-of', [Build::keyword('UPDATE OF'), $columns]) : Build::keyword($event->value);
        }
        return new Tree('trigger-events', $parts);
    }

    /**
     * The REFERENCING clause, omitted when no transition table is named.
     * @param list<Operand\TransitionTable> $transitions
     * @return list<Tree>
     */
    public static function transitions(array $transitions): array
    {
        if ($transitions === []) {
            return [];
        }
        return [Build::keyword('REFERENCING'), ...array_map(static fn (Operand\TransitionTable $table): Tree => new Tree('transition', [Build::keyword(strtoupper($table->version->value) . ' TABLE AS'), Build::identifier([$table->name], Dialect::PostgreSql)]), $transitions)];
    }

    /**
     * The parenthesized WHEN condition, omitted when absent.
     * @return list<Tree>
     */
    public static function condition(?Expression $condition): array
    {
        return $condition === null ? [] : [Build::keyword('WHEN'), Build::parentheses(Expressions::write($condition))];
    }

    /**
     * EXECUTE FUNCTION with every argument written as the string constant the function receives.
     */
    public static function invocation(Operand\TriggerInvocation $invocation): Tree
    {
        $arguments = array_map(static fn (string $argument): Tree => new Tree('literal', [new Atom('literal', Literal::encode($argument, Dialect::PostgreSql)[0])]), $invocation->arguments);
        return new Tree('trigger-invocation', [Build::keyword('EXECUTE FUNCTION'), Build::identifier($invocation->function->parts, Dialect::PostgreSql), new Tree('arguments', [new Atom('punctuation', '('), Build::separated($arguments), new Atom('punctuation', ')')])]);
    }
}
