<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\FromBinder;
use SqlSemantics\Binding\ProjectionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\MutationBinder;
use SqlSemantics\Binding\Statement\ValuesBinder;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Write\Merge;
use SqlSemantics\Model\Write\MergeAction;

/**
 * Binds matching and each MERGE branch in its own visible relation namespace.
 *
 * @visibility SqlSemantics
 */
final class MergeBinder
{
    /**
     * Retains ordered decisions without flattening conditional writes into a single update.
     */
    public function bind(Node $source, Node $statement, QueryContext $context): BoundStatement
    {
        $id = $context->ids->scope();
        $target = (new MutationBinder($context))->targets($statement, $id)[0] ?? null;
        $inputNode = Tree::child($statement, ['table_ref']);
        if ($target === null || $inputNode === null) {
            Tree::invalid($statement, 'MERGE inputs');
        }
        $input = (new FromBinder($context->tables, $context->ids, $context, scopeId: $id))->relation($inputNode);
        $destinations = new Scope($context->tables->identifiers, [$target], queries: $context);
        $scope = $destinations->combine($input->scope, $statement);
        $condition = (new ConflictBinder())->predicate(Tree::child($statement, ['a_expr']), $scope);
        if ($condition === null) {
            Tree::invalid($statement, 'MERGE matching condition');
        }
        $actions = array_map(fn (Node $node): MergeAction => $this->action($node, $target, $scope, $input->scope, $destinations), Tree::outer($statement, ['merge_when_clause']));
        $returning = Tree::child($statement, ['returning_clause']);
        $outputs = $returning === null ? [] : (new ProjectionBinder())->bind($returning, $scope);
        return new BoundStatement($id, $input->relation, $scope->relations, $outputs, null, false, [], null, null, $source, ctes: $context->ctes, kind: 'MERGE', targets: [$target], merge: new Merge($target, $input->relation, $condition, $actions));
    }

    /**
     * Applies match-specific visibility to predicates and input values, keeping targets separate.
     */
    public function action(Node $node, TableUse $target, Scope $scope, Scope $input, Scope $destinations): MergeAction
    {
        $matchNode = Tree::child($node, ['merge_when_tgt_matched', 'merge_when_tgt_not_matched', 'merge_when_src_not_matched']);
        $match = match ($matchNode?->name) {
            'merge_when_tgt_not_matched' => 'not-matched-by-target',
            'merge_when_src_not_matched' => 'not-matched-by-source',
            default => 'matched',
        };
        if ($matchNode !== null && in_array('NOT', array_map(static fn ($token): string => strtoupper($token->text), $matchNode->tokens()), true) && $match === 'matched') {
            $match = 'not-matched-by-source';
        }
        $scope = match ($match) {
            'not-matched-by-target' => $input,
            'not-matched-by-source' => $destinations,
            'matched' => $scope,
        };
        $condition = (new ConflictBinder())->predicate(Tree::child($node, ['opt_merge_when_condition']), $scope);
        $operation = Tree::child($node, ['merge_update', 'merge_insert', 'merge_delete']);
        if ($operation === null) {
            return new MergeAction($match, 'nothing', $condition, [], null, [], $node);
        }
        $action = substr($operation->name, strlen('merge_'));
        $assignments = $action === 'update' ? (new AssignmentBinder())->bind($operation, $scope, $destinations) : [];
        $rows = $action === 'insert' ? (new ValuesBinder())->rows($operation, $scope) : [];
        $insertion = $action === 'insert' ? (new InsertionBinder())->bind($operation, $target, $destinations, $rows, [], [], $destinations) : null;
        return new MergeAction($match, $action, $condition, $assignments, $insertion, $rows, $node);
    }
}
