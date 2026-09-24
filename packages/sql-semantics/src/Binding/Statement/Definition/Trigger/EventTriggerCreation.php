<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Trigger;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scalar\Intrinsic\FieldSpelling;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Trigger\DdlCommandTag;
use SqlSemantics\Model\Definition\Trigger\EventTriggerEvent;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateEventTriggerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE EVENT TRIGGER with its event name and command-tag filter.
 * @visibility SqlSemantics
 */
final class EventTriggerCreation
{
    /**
     * Rejects unknown events, filter variables other than one TAG, and tags that cannot fire the event.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): CreateEventTriggerStatement
    {
        $identifiers = $context->tables->identifiers;
        $name = $identifiers->name((Tree::child($source, ['name']) ?? throw new UnclassifiedSql('An event trigger requires its name.'))->tokens()[0]);
        $label = Tree::child($source, ['ColLabel']) ?? throw new UnclassifiedSql('An event trigger requires its event.');
        $event = EventTriggerEvent::tryFrom($identifiers->name($label->tokens()[0])) ?? throw new InvalidSql(InputViolation::TriggerDefinition, $label);
        $function = new QualifiedName($identifiers->parts(Tree::child($source, ['func_name']) ?? throw new UnclassifiedSql('An event trigger requires its function.')));
        $filters = Tree::outer($source, ['event_trigger_when_item']);
        if (count($filters) > 1) {
            throw new InvalidSql(InputViolation::TriggerDefinition, $filters[1]);
        }
        $tags = $filters === [] ? [] : self::tags($filters[0], $context);
        try {
            return new CreateEventTriggerStatement($origin, $name, $event, $function, $tags);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::TriggerDefinition, $source, $error);
        }
    }

    /**
     * Reads a TAG filter; tags are matched without regard to case.
     * @return list<DdlCommandTag>
     * @throws InvalidSql
     */
    public static function tags(Node $filter, QueryContext $context): array
    {
        $identifiers = $context->tables->identifiers;
        if ($identifiers->name($filter->tokens()[0]) !== 'tag') {
            throw new InvalidSql(InputViolation::TriggerDefinition, $filter);
        }
        $tags = [];
        $values = Tree::outer($filter, ['event_trigger_value_list'])[0] ?? $filter;
        foreach ($values->tokens() as $token) {
            if ($token->text !== ',') {
                $tags[] = DdlCommandTag::tryFrom(strtoupper(FieldSpelling::read($token, $identifiers))) ?? throw new InvalidSql(InputViolation::TriggerDefinition, $filter);
            }
        }
        return $tags;
    }
}
