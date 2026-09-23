<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Trigger;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\PostgreSqlRoles;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Trigger\TriggerFiring;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Binds event-trigger alterations and removal without relation-trigger operands.
 * @visibility SqlSemantics
 */
final class EventTriggerBinder
{
    /**
     * Classifies firing policy, name changes, ownership transfers, and removal separately.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $tokens = $source->tokens();
        if ($origin->dialect !== Dialect::PostgreSql || !in_array($source->name, ['AlterEventTrigStmt', 'RenameStmt', 'AlterOwnerStmt', 'DropStmt'], true) || ($tokens[1]->name ?? '') !== 'EVENT' || ($tokens[2]->name ?? '') !== 'TRIGGER') {
            return null;
        }
        $names = array_map(static fn (Node $node): string => $context->tables->identifiers->name($node->tokens()[0]), Tree::outer($source, ['name']));
        $name = $names[0] ?? throw new UnclassifiedSql('An event-trigger operation requires its name.');
        if ($source->name === 'DropStmt') {
            $behavior = Tree::child($source, ['opt_drop_behavior']);
            return new Statement\DropEventTriggersStatement($origin, Collections::nonEmpty($names), array_filter($source->children, static fn ($child): bool => $child instanceof Token && $child->name === 'IF_P') !== [], $behavior === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behavior))));
        }
        if ($source->name === 'RenameStmt') {
            return new Statement\RenameEventTriggerStatement($origin, $name, $names[1] ?? throw new UnclassifiedSql('An event-trigger rename requires its new name.'));
        }
        if ($source->name === 'AlterOwnerStmt') {
            $owner = Tree::child($source, ['RoleSpec']) ?? throw new UnclassifiedSql('An event-trigger owner change requires its new owner.');
            return new Statement\ChangeEventTriggerOwnerStatement($origin, $name, PostgreSqlRoles::read($owner));
        }
        $firing = Tree::child($source, ['enable_trigger']) ?? throw new UnclassifiedSql('An event-trigger firing change requires its policy.');
        return new Statement\AlterEventTriggerFiringStatement($origin, $name, TriggerFiring::from(strtoupper(Tree::text($firing))));
    }
}
