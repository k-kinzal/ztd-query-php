<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\FromBinder;
use SqlSemantics\Binding\ProjectionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\TableUse;

/**
 * Resolves write targets, assignment expressions, input queries, and returned rows.
 *
 * @visibility SqlSemantics
 */
final class MutationBinder
{
    /**
     * Shares statement identities and schema resolution with query inputs.
     */
    public function __construct(public readonly QueryContext $context)
    {
    }

    /**
     * Binds a mutation while keeping its result separate from its input query.
     */
    public function bind(Node $source, Node $statement): BoundStatement
    {
        $id = $this->context->ids->scope();
        $kind = StatementBinder::operation($statement);
        $targets = $this->targets($statement, $id);
        $scope = new Scope($this->context->tables->identifiers, $targets, queries: $this->context);
        $readNode = QueryNodes::local($statement, ['from_clause', 'using_clause', 'from'])[0] ?? null;
        $read = $readNode === null ? null : (new FromBinder($this->context->tables, $this->context->ids, $this->context, scopeId: $id))->bind($readNode);
        if ($read !== null) {
            $scope = $scope->combine($read->scope, $statement);
        }
        $scope = $this->conflictScope($scope, $targets, $statement, $id);
        $assignments = [];
        foreach ([...Tree::outer($statement, ['set_clause', 'update_elem']), ...array_reverse($statement->find('setlist'))] as $assignment) {
            $nameNode = Tree::child($assignment, ['set_target', 'simple_ident_nospvar', 'nm']);
            $value = Tree::child($assignment, ['a_expr', 'expr', 'expr_or_default']);
            if ($nameNode !== null && $value !== null) {
                $name = $scope->identifiers->parts($nameNode)[0];
                (new Scope($scope->identifiers, $targets))->column([$name], $nameNode);
                $assignments[$name] = (new ExpressionBinder())->bind($value, $scope);
            }
        }
        $whereNode = QueryNodes::local($statement, ['where_clause', 'opt_where_clause', 'where_or_current_clause', 'where_opt', 'where_opt_ret'])[0] ?? null;
        $whereExpr = $whereNode === null ? null : (Tree::outer($whereNode, ['a_expr', 'expr'])[0] ?? null);
        $where = $whereExpr === null ? null : (new ExpressionBinder())->bind($whereExpr, $scope);
        $returning = QueryNodes::local($statement, ['returning_clause', 'where_opt_ret'])[0] ?? null;
        $outputs = $returning === null || !str_contains(strtoupper(Tree::text($returning)), 'RETURNING') ? [] : (new ProjectionBinder())->bind($returning, $scope);
        $queries = [];
        foreach (Tree::outer($statement, ['SelectStmt', 'query_expression', 'select']) as $query) {
            $queries[] = $this->context->bind($query);
        }
        $values = (new ValuesBinder())->rows($statement, $scope);
        return new BoundStatement($id, $read->relation ?? ($targets[0] ?? null), $scope->relations, $outputs, $where, false, [], null, null, $source, ctes: $this->context->ctes, kind: $kind, targets: $targets, assignments: $assignments, queries: $queries, rows: $values);
    }

    /**
     * @return list<TableUse>
     */
    public function targets(Node $statement, string $id): array
    {
        $tables = $this->context->tables;
        $from = QueryNodes::local($statement, ['table_reference_list'])[0] ?? null;
        if ($from !== null) {
            return (new FromBinder($tables, $this->context->ids, $this->context, scopeId: $id))->bind($from)?->scope->relations ?? [];
        }
        $node = QueryNodes::local($statement, ['insert_target', 'relation_expr_opt_alias', 'relation_expr', 'table_ident', 'xfullname'])[0] ?? null;
        if ($node === null) {
            return [];
        }
        $name = Tree::outer($node, ['qualified_name', 'table_ident', 'nm'])[0] ?? $node;
        $declaration = $tables->resolve($tables->identifiers->parts($name), $name);
        $alias = Tree::child($node, ['ColId', 'as']);
        return [new TableUse($this->context->ids->relation(), $id, $declaration, $alias === null ? null : $tables->identifiers->parts($alias)[0], $node)];
    }
    /**
     * @param list<TableUse> $targets
     */
    public function conflictScope(Scope $scope, array $targets, Node $source, string $id): Scope
    {
        if ($targets === [] || !str_contains(strtoupper(Tree::text($source)), 'ON CONFLICT')) {
            return $scope;
        }
        $excluded = new TableUse($this->context->ids->relation(), $id, $targets[0]->declaration, 'excluded', $source);
        $parent = new Scope($scope->identifiers, [$excluded], queries: $this->context);
        return new Scope($scope->identifiers, $scope->relations, $scope->extensions, $parent, $this->context, $scope->merged);
    }

}
