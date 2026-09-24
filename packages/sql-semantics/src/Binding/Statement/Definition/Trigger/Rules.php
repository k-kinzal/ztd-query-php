<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Trigger;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Definition\Relation\Rule\RuleEvent;
use SqlSemantics\Model\Definition\Relation\Rule\RuleInvariant;
use SqlSemantics\Model\Relation\TriggerRow;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule as Statement;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\Notification\NotifyStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE RULE with its condition and actions in a scope where OLD and NEW are the rule's table.
 * @visibility SqlSemantics
 */
final class Rules
{
    /**
     * Separates rules that do nothing from rules with actions; an action list of only empty commands does nothing.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): Statement\CreateEmptyRuleStatement|Statement\CreateCommandRuleStatement
    {
        $identifiers = $context->tables->identifiers;
        $name = $identifiers->name((Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A rule requires its name.'))->tokens()[0]);
        $table = RelationTriggers::table(Tree::child($source, ['qualified_name']) ?? throw new UnclassifiedSql('A rule requires its table.'), $origin, $context);
        $event = RuleEvent::from(strtoupper((Tree::child($source, ['event']) ?? throw new UnclassifiedSql('A rule requires its event.'))->tokens()[0]->text));
        $rows = array_map(static fn (RowVersion $version): TriggerRow => new TriggerRow($context->ids->relation(), $table->scopeId, $table->declaration, $table->source, $version), RuleInvariant::images($event));
        $scope = new Scope($identifiers, $rows, queries: $context);
        $where = Tree::child($source, ['where_clause']);
        $condition = $where === null ? null : (new ExpressionBinder())->bind(Tree::child($where, ['a_expr']) ?? throw new UnclassifiedSql('WHERE requires its condition.'), $scope);
        $instead = strtoupper(Tree::text(Tree::child($source, ['opt_instead']) ?? $source)) === 'INSTEAD';
        $actions = array_map(static fn (Node $action): BoundQuery|InsertStatement|UpdateStatement|DeleteStatement|NotifyStatement => self::action($action, $scope, $context), Tree::outer($source, ['RuleActionStmt']));
        try {
            if ($actions === []) {
                return new Statement\CreateEmptyRuleStatement($origin, $name, $table, $event, $instead, $condition, Tree::child($source, ['opt_or_replace']) !== null);
            }
            return new Statement\CreateCommandRuleStatement($origin, $name, $table, $event, $actions, $instead, $condition, Tree::child($source, ['opt_or_replace']) !== null);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RewriteRule, $source, $error);
        }
    }

    /**
     * Binds one action with the same native statement forms as direct SQL.
     * @throws UnclassifiedSql
     */
    public static function action(Node $action, Scope $scope, QueryContext $context): BoundQuery|InsertStatement|UpdateStatement|DeleteStatement|NotifyStatement
    {
        $command = Tree::child($action, ['SelectStmt', 'InsertStmt', 'UpdateStmt', 'DeleteStmt', 'NotifyStmt']) ?? throw new UnclassifiedSql('A rule action requires its command.');
        $bound = (new StatementBinder($context->tables))->node($command, $command, $context, $scope);
        if ($bound instanceof BoundQuery || $bound instanceof InsertStatement || $bound instanceof UpdateStatement || $bound instanceof DeleteStatement || $bound instanceof NotifyStatement) {
            return $bound;
        }
        throw new UnclassifiedSql('Unclassified rule action: ' . Tree::text($command));
    }
}
