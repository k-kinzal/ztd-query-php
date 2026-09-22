<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use LogicException;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\FromBinder;
use SqlSemantics\Binding\ProjectionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\MutationBinder;
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
        $actions = array_map(fn (Node $node): MergeAction => $this->action($node, $target, $scope, $input->scope, $destinations), \SqlSemantics\Binding\Query\QueryNodes::local($statement, ['merge_when_clause']));
        $returning = Tree::child($statement, ['returning_clause']);
        $outputs = $returning === null ? [] : (new ProjectionBinder())->bind($returning, $scope);
        return new \SqlSemantics\Model\Statement\MergeStatement(new \SqlSemantics\Model\Statement\Origin($id, $source, $context->tables->identifiers->dialect), new Merge($target, $input->relation, $condition, $actions), $outputs, (new \SqlSemantics\Binding\Query\CteBinder())->clause($source, $context));
    }

    /**
     * Applies match-specific visibility to predicates and input values, keeping targets separate.
     * @throws LogicException
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public function action(Node $node, TableUse $target, Scope $scope, Scope $input, Scope $destinations): MergeAction
    {
        $match = $this->matchKind($node);
        $scope = match ($match) {
            \SqlSemantics\Model\Write\Decision\MatchKind::MissingTarget => $input,
            \SqlSemantics\Model\Write\Decision\MatchKind::MissingSource => $destinations,
            \SqlSemantics\Model\Write\Decision\MatchKind::Matched => $scope,
        };
        $condition = (new ConflictBinder())->predicate(Tree::child($node, ['opt_merge_when_condition']), $scope);
        $operation = Tree::child($node, ['merge_update', 'merge_insert', 'merge_delete']);
        if ($operation === null) {
            return new \SqlSemantics\Model\Write\Decision\MergeNothing($match, $condition, $node);
        }
        $action = substr($operation->name, strlen('merge_'));
        $assignments = $action === 'update' ? (new AssignmentBinder())->bind($operation, $scope, $destinations) : [];
        $rows = $action === 'insert' ? WriteInputs::rows($operation, $scope) : [];
        $insertion = $action === 'insert' ? (new InsertionBinder())->bind($operation, $target, $destinations, $rows, [], [], $destinations) : null;
        return match ($action) {
            'update' => new \SqlSemantics\Model\Write\Decision\MergeUpdate($match, $condition, $node, $assignments),
            'delete' => new \SqlSemantics\Model\Write\Decision\MergeDelete($match, $condition, $node),
            'insert' => $insertion === null ? throw new LogicException('A MERGE insertion requires its destination.') : ((new InsertionBinder())->defaultValues($operation)
                ? new \SqlSemantics\Model\Write\Decision\MergeInsertDefaults($match, $condition, $node, $insertion)
                : new \SqlSemantics\Model\Write\Decision\MergeRowInsertion($match, $condition, $node, $insertion, new \SqlSemantics\Model\Write\InputRow($scope->identifiers->dialect, $rows[0] ?? throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('A MERGE insertion requires one row.')))),
            default => throw new LogicException('Unclassified MERGE action: ' . $action),
        };
    }
    /**
     * Selects the row-presence condition governing a MERGE branch's visible relations.
     */
    public function matchKind(Node $node): \SqlSemantics\Model\Write\Decision\MatchKind
    {
        $match = Tree::child($node, ['merge_when_tgt_matched', 'merge_when_tgt_not_matched', 'merge_when_src_not_matched']);
        return match ($match?->name) {
            'merge_when_tgt_not_matched' => \SqlSemantics\Model\Write\Decision\MatchKind::MissingTarget,
            'merge_when_src_not_matched' => \SqlSemantics\Model\Write\Decision\MatchKind::MissingSource,
            default => $match !== null && in_array('NOT', array_map(static fn ($token): string => strtoupper($token->text), $match->tokens()), true)
                ? \SqlSemantics\Model\Write\Decision\MatchKind::MissingSource
                : \SqlSemantics\Model\Write\Decision\MatchKind::Matched,
        };
    }

}
