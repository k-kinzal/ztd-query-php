<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\FromBinder;
use SqlSemantics\Binding\ProjectionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\SelectModifiersBinder;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\TypeResolution;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

/**
 * Binds query scopes, CTE declarations, grouping, and compound query operands.
 *
 * @visibility SqlSemantics
 */
final class QueryBinder
{
    /**
     * Uses a statement-wide identity allocator and a lexical CTE environment.
     */
    public function __construct(public readonly QueryContext $context)
    {
    }

    /**
     * Resolves one query scope and retains all its nested relational stages.
     */
    public function bind(Node $source, ?Scope $parent = null): BoundQuery
    {
        $id = $this->context->ids->scope();
        $context = $this->with($source, $parent);
        $body = QueryNodes::body($source);
        $operator = QueryNodes::setOperator($body);
        if ($operator !== null) {
            return $this->compound($source, $body, $context, $id, $operator, $parent);
        }
        $fromNode = $body->name === 'derived_table_list' ? $body : (QueryNodes::local($body, ['from_clause', 'select_from', 'from'])[0] ?? null);
        $inputs = new FromBinder($context->tables, $context->ids, $context, $parent, $id);
        $from = $fromNode === null ? $inputs->explicit($body) : $inputs->bind($fromNode);
        $scope = $from->scope ?? new Scope($context->tables->identifiers, parent: $parent, queries: $context);
        $where = $this->expressions($body, ['where_clause', 'opt_where_clause', 'where_opt'], $scope)[0] ?? null;
        $having = $this->expressions($body, ['having_clause', 'opt_having_clause', 'having_opt'], $scope)[0] ?? null;
        foreach ([$where, $having] as $predicate) {
            if ($predicate !== null) {
                (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->predicate($predicate);
            }
        }
        $values = (new \SqlSemantics\Binding\Statement\ValuesBinder())->rows($body, $scope);
        $outputs = $values === [] ? (new ProjectionBinder())->bind($body, $scope) : (new \SqlSemantics\Binding\Statement\ValuesBinder())->outputs($values, $body, $scope);
        $options = QueryNodes::local($body, ['distinct_clause', 'select_options', 'distinct']);
        $distinct = $options !== [] && str_contains(strtoupper(Tree::text($options[0])), 'DISTINCT');
        $tail = new SelectModifiersBinder();
        [$limit, $offset] = $tail->pagination($source, $scope);
        $groups = $this->expressions($body, ['group_clause', 'opt_group_clause', 'groupby_opt'], $scope);
        $clauses = [];
        foreach (['window_clause', 'opt_window_clause', 'windowdefn_list', 'distinct_clause', 'into_clause', 'locking_clause', 'for_locking_clause'] as $name) {
            if (QueryNodes::local($body, [$name]) !== []) {
                $clauses[$name] = $this->expressions($body, [$name], $scope);
            }
        }
        $class = $values !== [] ? \SqlSemantics\Model\Statement\ValuesStatement::class : (strtoupper(Tree::text($body->tokens()[0] ?? $body)) === 'TABLE' ? \SqlSemantics\Model\Statement\TableStatement::class : \SqlSemantics\Model\BoundSelect::class);
        return new $class($id, $from?->relation, $scope->relations, $outputs, $where, $distinct, $tail->ordering($source, $scope, $outputs), $limit, $offset, $source, $groups, $having, $context->ctes, clauses: $clauses, rows: $values, withTies: str_contains(strtoupper(Tree::text(QueryNodes::local($source, ['limit_clause'])[0] ?? new Node('empty', 0, []))), 'WITH TIES'), syntaxClauses: QueryNodes::clauses($source));
    }

    /**
     * @param list<string> $names
     * @return list<Expression>
     */
    public function expressions(Node $body, array $names, Scope $scope): array
    {
        $expressions = [];
        foreach (QueryNodes::local($body, $names) as $clause) {
            foreach (Tree::outer($clause, ['a_expr', 'expr']) as $node) {
                $expressions[] = (new ExpressionBinder())->bind($node, $scope);
            }
        }
        return $expressions;
    }

    /**
     * Binds CTEs in declaration order, with recursive names visible to their body.
     *
     * @throws SemanticException
     */
    public function with(Node $source, ?Scope $parent): QueryContext
    {
        $ctes = $this->context->ctes;
        $with = QueryNodes::local($source, ['with_clause', 'wqlist']);
        if ($with === []) {
            $with = Tree::outer($source, ['with_clause', 'wqlist', 'simple_select', 'query_specification', 'oneselect']);
            $with = array_values(array_filter($with, static fn (Node $node): bool => in_array($node->name, ['with_clause', 'wqlist'], true)));
        }
        foreach ($with as $clause) {
            foreach (Tree::outer($clause, ['common_table_expr', 'wqitem']) as $cte) {
                $nameNode = Tree::child($cte, ['name', 'ident', 'withnm']);
                $queryNode = Tree::outer($cte, ['SelectStmt', 'subquery', 'select', 'InsertStmt', 'UpdateStmt', 'DeleteStmt', 'MergeStmt'])[0] ?? null;
                if ($nameNode === null || $queryNode === null) {
                    throw new SemanticException('invalid-cte', 'A CTE requires a name and a query.', $cte);
                }
                $name = $this->context->tables->identifiers->parts($nameNode)[0];
                $context = new QueryContext($this->context->tables, $this->context->ids, $ctes);
                if (in_array($queryNode->name, ['InsertStmt', 'UpdateStmt', 'DeleteStmt', 'MergeStmt'], true)) {
                    $ctes[$name] = $this->rename((new StatementBinder($context->tables))->node($queryNode, $queryNode, $context), $cte);
                    continue;
                }
                $body = QueryNodes::body($queryNode);
                if (QueryNodes::setOperator($body) !== null) {
                    $branches = $this->branches($body);
                    $ctes[$name] = $this->rename($context->bind($branches[0], $parent), $cte);
                    $context = new QueryContext($context->tables, $context->ids, $ctes);
                }
                $ctes[$name] = $this->rename($context->bind($queryNode, $parent), $cte);
            }
        }
        return new QueryContext($this->context->tables, $this->context->ids, $ctes);
    }

    /**
     * Applies a CTE column alias list without changing its expression graph.
     */
    public function rename(BoundStatement $query, Node $cte): BoundStatement
    {
        $aliases = Tree::child($cte, ['opt_name_list', 'opt_derived_column_list', 'eidlist_opt']);
        if ($aliases === null) {
            return $query;
        }
        $names = array_values(array_filter($this->context->tables->identifiers->parts($aliases), static fn (string $name): bool => !in_array($name, ['(', ')', ','], true)));
        return $query->withOutputNames($names);
    }

    /**
     * @return non-empty-list<Node>
     *
     * @throws SemanticException
     */
    public function branches(Node $body): array
    {
        $branches = [];
        foreach ($body->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['select_clause', 'query_expression_body', 'query_specification', 'selectnowith', 'oneselect', 'select_part2', 'select_init', 'select_paren', 'select_derived_union', 'select_derived'], true)) {
                $branches[] = $child;
            }
        }
        if ($branches === []) {
            throw new SemanticException('invalid-set-operation', 'A compound query requires query operands.', $body);
        }
        return $branches;
    }

    /**
     * Combines output types by ordinal while retaining each branch's dependencies.
     *
     * @throws SemanticException
     */
    public function compound(Node $source, Node $body, QueryContext $context, string $id, string $operator, ?Scope $parent): BoundQuery
    {
        $branches = array_map(static fn (Node $node): BoundQuery => $context->bind($node, $parent), $this->branches($body));
        $outputs = [];
        foreach ($branches[0]->outputs as $index => $output) {
            $operands = [];
            foreach ($branches as $branch) {
                if (count($branch->outputs) !== count($branches[0]->outputs)) {
                    $context->tables->diagnostics->report('set-column-count', 'Compound query operands must have the same width.', $body);
                }
                if (!isset($branch->outputs[$index])) {
                    continue;
                }
                $value = $branch->outputs[$index]->expression;
                $operands[] = $value->kind === ExpressionKind::Cast && $value->symbol === 'implicit' && isset($value->operands[0]) && $value->operands[0]->type->name === 'unknown' ? $value->operands[0] : $value;
            }
            $type = (new TypeResolution($context->tables->identifiers->dialect, $context->tables->diagnostics))->common($operands, $body);
            $nullable = array_filter($operands, static fn (Expression $value): bool => $value->nullability !== Nullability::NotNull) !== [];
            $outputs[] = new OutputColumn($index, $output->name, new Expression(ExpressionKind::Operator, $type, $nullable ? Nullability::MaybeNull : Nullability::NotNull, $body, $operands, symbol: $operator));
        }
        $scope = new Scope($context->tables->identifiers, parent: $parent, queries: $context);
        $tail = new SelectModifiersBinder();
        [$limit, $offset] = $tail->pagination($source, $scope);
        return new \SqlSemantics\Model\Statement\CompoundStatement($id, null, [], $outputs, null, !str_ends_with($operator, 'ALL'), $tail->ordering($source, $scope, $outputs), $limit, $offset, $source, ctes: $context->ctes, branches: $branches, setOperator: $operator);
    }
}
