<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Trigger;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Relation;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Trigger;

/**
 * Binds a SQLite trigger and supplies the event's OLD and NEW row namespaces.
 * @visibility SqlSemantics
 */
final class SqliteTriggerBinder
{
    /**
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, Node $header, QueryContext $context): CreateSqliteTriggerStatement
    {
        $name = Tree::child($header, ['nm']);
        $subject = Tree::child($header, ['fullname']);
        $eventNode = Tree::child($header, ['trigger_event']);
        if ($name === null || $subject === null || $eventNode === null) {
            throw new UnclassifiedSql('A trigger requires a name, subject and event.');
        }
        $identifiers = $context->tables->identifiers;
        $parts = $identifiers->parts($name);
        $suffix = Tree::child($header, ['dbnm']);
        if ($suffix !== null) {
            array_push($parts, ...$identifiers->parts($suffix));
        }
        $table = $context->tables->resolve($identifiers->parts($subject), $subject);
        $reference = new Relation\TableReference($context->ids->relation(), $origin->scopeId, $table, $context->tables->name($identifiers->parts($subject), $table), null, $subject);
        $event = self::event($eventNode, $context);
        $scope = self::scope($reference, $event, $context);
        $when = Tree::child($header, ['when_clause']);
        $condition = $when === null ? null : Tree::child($when, ['expr']);
        $timing = Tree::child($header, ['trigger_time']);
        $body = TriggerSteps::bind($source, $context, $scope);
        return new CreateSqliteTriggerStatement($origin, new Relation\QualifiedName($parts), $reference, $timing === null ? Trigger\Timing::Before : Trigger\Timing::from(strtoupper(Tree::text($timing))), $event, $body, $condition === null ? null : (new ExpressionBinder())->bind($condition, $scope), Tree::child($header, ['temp']) !== null, Tree::child($header, ['ifnotexists']) !== null);
    }

    /**
     * Distinguishes updates of any column from updates of a named column list.
     * @throws UnclassifiedSql
     */
    public static function event(Node $source, QueryContext $context): Trigger\Event
    {
        $event = Trigger\WriteEvent::from(strtoupper($source->tokens()[0]->text ?? ''));
        $columns = Tree::child($source, ['idlist']);
        if ($columns === null) {
            return $event;
        }
        $names = array_map(static fn (Node $name): string => $context->tables->identifiers->parts($name)[0], Tree::outer($columns, ['nm']));
        if ($names === []) {
            throw new UnclassifiedSql('UPDATE OF requires a column list.');
        }
        return new Trigger\UpdatedColumns($names);
    }

    /**
     * Exposes only the row images defined for the triggering operation.
     */
    public static function scope(Relation\TableReference $subject, Trigger\Event $event, QueryContext $context): Scope
    {
        $versions = match ($event->operation()) {
            Trigger\WriteEvent::Insert => [Trigger\RowVersion::New],
            Trigger\WriteEvent::Delete => [Trigger\RowVersion::Old],
            Trigger\WriteEvent::Update => [Trigger\RowVersion::Old, Trigger\RowVersion::New],
        };
        $rows = array_map(static fn (Trigger\RowVersion $version): Relation\TriggerRow => new Relation\TriggerRow($context->ids->relation(), $subject->scopeId, $subject->declaration, $subject->source, $version), $versions);
        return new Scope($context->tables->identifiers, $rows, queries: $context);
    }
}
