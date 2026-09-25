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
use SqlSemantics\Model\Expression;
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
        $values = self::lead($body) === 'VALUES' ? (new \SqlSemantics\Binding\Statement\ValuesBinder())->rows($body, $scope) : [];
        $outputs = $values === [] ? (new ProjectionBinder())->bind($body, $scope) : (new \SqlSemantics\Binding\Statement\ValuesBinder())->outputs($values, $body, $scope);
        $projectionOptions = new ProjectionOptions();
        $quantifier = $projectionOptions->quantifier($body, $scope);
        $tail = new SelectModifiersBinder();
        $tailSource = QueryNodes::modifierScope($source, $body);
        [$limit, $offset] = $tail->pagination($tailSource, $scope);
        $withTies = in_array('TIES', Tree::keywords(QueryNodes::local($tailSource, ['limit_clause'])[0] ?? new Node('empty', 0, [])), true);
        [$groups, $distinctGroupingSets] = Grouping\GroupingBinder::bind($body, $scope);
        $origin = new \SqlSemantics\Model\Statement\Origin($id, $source, $context->tables->identifiers->dialect);
        $ordering = $tail->ordering($tailSource, $scope, $outputs);
        if ($values !== []) {
            return new \SqlSemantics\Model\Statement\ValuesStatement($origin, $values, $ordering, $limit, $offset, $withTies, (new CteBinder())->clause($source, $context), LockingBinder::values($source, $scope));
        }
        if (self::lead($body) === 'TABLE') {
            if (!$from?->relation instanceof \SqlSemantics\Model\Relation\NamedTableReference && !$from?->relation instanceof \SqlSemantics\Model\Relation\CteReference) {
                Tree::invalid($source, 'TABLE relation');
            }
            return new \SqlSemantics\Model\Statement\TableStatement($origin, $from->relation, $ordering, $limit, $offset, $withTies, (new CteBinder())->clause($source, $context), LockingBinder::bind($source, $scope));
        }
        return new \SqlSemantics\Model\BoundSelect($origin, $from?->relation, $outputs, $where, $quantifier, $ordering, $limit, $offset, $groups, $having, (new CteBinder())->clause($source, $context), withTies: $withTies, windows: $projectionOptions->windows($body, $scope), locks: LockingBinder::bind($source, $scope), hints: $origin->dialect === \SqlSemantics\Dialect::MySql ? OptimizerHints::bind($body) : [], options: QueryBlockOptions::bind($body, $context), distinctGroupingSets: $distinctGroupingSets, qualify: $this->expressions($body, ['opt_qualify_clause'], $scope)[0] ?? null);
    }

    /**
     * Returns the first keyword of a query body after its opening parentheses, so a parenthesized VALUES or TABLE keeps its form.
     */
    public static function lead(Node $body): string
    {
        foreach ($body->tokens() as $token) {
            if ($token->text !== '(') {
                return strtoupper($token->text);
            }
        }
        return '';
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
        $clause = CteNodes::clause($source);
        $with = $clause === null ? [] : [$clause];
        $definitions = [];
        foreach ($with as $withNode) {
            foreach (Tree::outer($withNode, ['common_table_expr', 'wqitem']) as $cte) {
                $nameNode = Tree::child($cte, ['name', 'ident', 'withnm']);
                $queryNode = Tree::outer($cte, ['SelectStmt', 'subquery', 'select', 'InsertStmt', 'UpdateStmt', 'DeleteStmt', 'MergeStmt'])[0] ?? null;
                if ($nameNode === null || $queryNode === null) {
                    throw new SemanticException('invalid-cte', 'A CTE requires a name and a query.', $cte);
                }
                $name = $this->context->tables->identifiers->parts($nameNode)[0];
                foreach ($definitions as $definition) {
                    if ($this->context->tables->identifiers->equal($name, $definition->name)) {
                        throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::DuplicateCte, $cte);
                    }
                }
                $context = new QueryContext($this->context->tables, $this->context->ids, $ctes, parameterTypes: $this->context->parameterTypes);
                if (in_array($queryNode->name, ['InsertStmt', 'UpdateStmt', 'DeleteStmt', 'MergeStmt'], true)) {
                    $ctes[$name] = CteBinder::definition($cte, (new StatementBinder($context->tables))->node($queryNode, $queryNode, $context), $context);
                    $definitions[] = $ctes[$name];
                    continue;
                }
                $body = QueryNodes::body($queryNode);
                if (QueryNodes::setOperator($body) !== null) {
                    $branches = $this->branches($body);
                    $ctes[$name] = CteBinder::definition($cte, QueryBlockOptions::nested($context->bind($branches[0], $parent), $cte, $context), $context);
                    $context = new QueryContext($context->tables, $context->ids, $ctes, parameterTypes: $context->parameterTypes);
                }
                $ctes[$name] = CteBinder::definition($cte, QueryBlockOptions::nested($context->bind($queryNode, $parent), $cte, $context), $context);
                $definitions[] = $ctes[$name];
            }
        }
        $recursive = CteBinder::recursive($source, $clause);
        foreach ($definitions as $definition) {
            if (!$recursive && ($definition->search !== null || $definition->cycle !== null)) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::RecursiveQueryClause, $clause ?? $source);
            }
        }
        return new QueryContext($this->context->tables, $this->context->ids, $ctes, $definitions === [] ? null : new \SqlSemantics\Model\Query\WithClause($definitions, $recursive), $this->context->parameterTypes);
    }

    /**
     * @return array{Node, Node}
     *

     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public function branches(Node $body): array
    {
        $branches = [];
        foreach ($body->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['select_clause', 'query_expression_body', 'legacy_compound', 'query_specification', 'selectnowith', 'oneselect', 'select_part2', 'select_init', 'select_paren', 'select_derived_union', 'select_derived', 'create_select', 'create_view_select'], true)) {
                $branches[] = $child;
            }
        }
        if (count($branches) !== 2) {
            throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('A set operation requires exactly two query operands: ' . $body->toString());
        }
        if ($body->name === 'selectnowith') {
            $right = $branches[1];
            $branches[1] = new Node($right->name, $right->ordinal, array_values(array_filter($right->children, static fn ($child): bool => !$child instanceof Node || !in_array($child->name, ['orderby_opt', 'limit_opt'], true))));
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
        $lock = $context->tables->identifiers->dialect === \SqlSemantics\Dialect::PostgreSql ? LockingBinder::setOperationLock($source, $body) : null;
        if ($lock !== null) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::SetOperationLock, $lock);
        }
        $operands = $this->branches($body);
        $trailing = LockingBinder::trailing($source, $body);
        if ($trailing !== []) {
            $operands[1] = new Node('query_expression_with_opt_locking_clauses', $operands[1]->ordinal, [$operands[1], ...$trailing]);
        }
        $branches = array_map(static fn (Node $node): BoundQuery => $context->bind($node, $parent), $operands);
        QueryBlockOptions::nested($branches[1], $body, $context);
        $leftWidth = \SqlSemantics\Model\Validation\RowShape::width($branches[0]);
        $rightWidth = \SqlSemantics\Model\Validation\RowShape::width($branches[1]);
        if ($leftWidth !== null && $rightWidth !== null && $leftWidth !== $rightWidth) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::SetWidth, $body);
        }
        $outputs = [];
        foreach ($branches[0]->resultColumns() as $index => $output) {
            $operands = [];
            foreach ($branches as $branch) {
                if (!isset($branch->resultColumns()[$index])) {
                    continue;
                }
                $value = $branch->resultColumns()[$index]->expression;
                $operands[] = \SqlSemantics\Model\Query\AlternativeFacts::setInput($value);
            }
            $type = (new TypeResolution($context->tables->identifiers->dialect, $context->tables->diagnostics))->common($operands, $body);
            $nullable = array_filter($operands, static fn (Expression $value): bool => $value->nullability !== Nullability::NotNull) !== [];
            $outputs[] = new OutputColumn($index, $output->name, new \SqlSemantics\Model\Scalar\Value\SetColumn(new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $nullable ? Nullability::MaybeNull : Nullability::NotNull, []), $body, \SqlSemantics\Model\Query\SetOperator::from($operator), $operands));
        }
        $scope = new Scope($context->tables->identifiers, parent: $parent, queries: $context);
        $tail = new SelectModifiersBinder();
        $tailSource = $body->name === 'selectnowith' ? (Tree::child($body, ['oneselect']) ?? $source) : QueryNodes::compoundTail($source, $body);
        [$limit, $offset] = $tail->pagination($tailSource, $scope);
        return new \SqlSemantics\Model\Statement\CompoundStatement(new \SqlSemantics\Model\Statement\Origin($id, $source, $context->tables->identifiers->dialect), $branches[0], $branches[1], \SqlSemantics\Model\Query\SetOperator::from($operator), $tail->ordering($tailSource, $scope, $outputs), $limit, $offset, ctes: (new CteBinder())->clause($source, $context));
    }
}
