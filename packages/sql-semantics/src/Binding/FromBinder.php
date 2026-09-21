<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Binding\Query\QueryRelation;
use SqlSemantics\Binding\Query\RelationFactory;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\TableUse;

/**
 * Binds relation trees, keeping ON visibility and NULL extension at their correct stages.
 *
 * @visibility SqlSemantics
 */
final class FromBinder
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly TableResolver $tables, public readonly IdentitySequence $ids, public readonly ?QueryContext $queries = null, public readonly ?Scope $parent = null, public readonly string $scopeId = 's0')
    {
    }

    /**
     * TABLE name is a query over all columns of the named relation.
     */
    public function explicit(Node $body): ?BoundRelation
    {
        $children = Tree::significant($body);
        if ($children === [] || strtoupper(Tree::text($children[0])) !== 'TABLE') {
            return null;
        }
        $name = Tree::outer($body, ['qualified_name', 'table_ident'])[0] ?? null;
        if ($name === null) {
            Tree::invalid($body, 'TABLE relation');
        }
        return $this->table($body, $this->tables->identifiers->parts($name), null);
    }

    /**
     * Builds the FROM tree in SQL binding order.
     */
    public function bind(Node $from): ?BoundRelation
    {
        if ($this->tables->identifiers->dialect === \SqlSemantics\Dialect::MySql && str_starts_with(strtoupper(Tree::text($from)) . ' ', 'FROM DUAL ')) {
            return null;
        }
        $nodes = Tree::outer($from, ['table_ref', 'table_reference', 'seltablist']);
        $result = null;
        foreach ($nodes as $node) {
            $lateral = str_starts_with(strtoupper(Tree::text($node)), 'LATERAL') || QueryNodes::local($node, ['func_table', 'table_function', 'json_table', 'xmltable']) !== [];
            $binder = $lateral && $result !== null ? new self($this->tables, $this->ids, $this->queries, $result->scope, $this->scopeId) : $this;
            $right = $binder->relation($node);
            $result = $result === null ? $right : $this->join($result, $right, JoinKind::Cross, null, $from, $this->ids->join());
        }
        if ($result === null && Tree::hasTokens($from)) {
            Tree::invalid($from, 'FROM clause');
        }

        return $result;
    }

    /**
     * Binds one table reference or nested join.
     */
    public function relation(Node $node): BoundRelation
    {
        if ($node->name === 'seltablist') {
            return $this->sqlite($node);
        }
        $joins = QueryNodes::local($node, ['joined_table', 'join_table']);
        if ($joins !== []) {
            $relation = $this->joined($joins[0]);
            $alias = Tree::child($node, ['alias_clause', 'opt_alias_clause']);
            return $alias === null ? $relation : (new RelationFactory())->alias($relation, $alias, $node, $this->queries ?? new QueryContext($this->tables, $this->ids), $this->scopeId);
        }
        $derived = Tree::outer($node, ['select_with_parens', 'table_subquery', 'select_derived_union', 'select_derived2'])[0] ?? null;
        $factor = Tree::outer($node, ['table_factor'])[0] ?? null;
        if ($derived === null && $factor !== null && QueryNodes::isBody($factor)) {
            $derived = $factor;
        }
        if ($derived !== null) {
            return $this->derived($node, $derived, QueryNodes::local($node, ['alias_clause', 'opt_table_alias'])[0] ?? null);
        }
        $function = QueryNodes::local($node, ['func_table', 'table_function', 'json_table', 'xmltable'])[0] ?? null;
        if ($function !== null) {
            return (new RelationFactory())->function($node, $function, $this->queries ?? new QueryContext($this->tables, $this->ids), $this->parent, $this->scopeId);
        }
        $base = Tree::outer($node, ['relation_expr', 'single_table', 'table_factor'])[0] ?? null;
        if ($base === null) {
            Tree::invalid($node, 'relation');
        }
        $name = Tree::outer($base, ['qualified_name', 'table_ident'])[0] ?? null;
        if ($name === null) {
            Tree::invalid($base, 'table reference');
        }

        $alias = Tree::outer($node, ['opt_alias_clause', 'opt_table_alias'])[0] ?? null;

        return $this->table($node, $this->tables->identifiers->parts($name), $alias);
    }

    /**
     * Binds a PostgreSQL or MySQL joined-table grammar node.
     */
    public function joined(Node $node): BoundRelation
    {
        $nested = Tree::child($node, ['joined_table']);
        if ($nested !== null) {
            return $this->joined($nested);
        }
        $references = Tree::outer($node, ['table_ref', 'table_reference', 'table_factor']);
        if (count($references) !== 2) {
            Tree::invalid($node, 'join operands');
        }
        $kindNode = Tree::child($node, ['join_type', 'outer_join_type', 'inner_join_type', 'natural_join_type']);
        $joinWords = implode(' ', array_map(Tree::text(...), array_values(array_filter($node->children, static fn ($child): bool => $child instanceof \SqlParser\Lexer\Token))));
        $kind = $this->kind($kindNode === null ? $joinWords : Tree::text($kindNode), $node);
        $qualifier = Tree::child($node, ['join_qual']);
        $condition = $qualifier === null ? Tree::child($node, ['expr']) : (Tree::outer($qualifier, ['a_expr'])[0] ?? null);
        if ($kindNode === null && in_array('CROSS', array_map(static fn ($token): string => strtoupper($token->text), $node->tokens()), true)) {
            $kind = JoinKind::Cross;
        }
        $id = $this->ids->join();
        $left = $this->relation($references[0]);
        $lateral = str_starts_with(strtoupper(Tree::text($references[1])), 'LATERAL') || QueryNodes::local($references[1], ['func_table', 'table_function', 'json_table', 'xmltable']) !== [];
        $right = ($lateral ? new self($this->tables, $this->ids, $this->queries, $left->scope, $this->scopeId) : $this)->relation($references[1]);
        if (($qualifier !== null && str_starts_with(strtoupper(Tree::text($qualifier)), 'USING')) || str_contains(strtoupper(Tree::text($kindNode ?? $node)), 'NATURAL')) {
            return (new Query\UsingJoin())->bind($left, $right, $kind, $node, $id, $qualifier);
        }
        $using = Tree::child($node, ['using_list']);
        if ($using !== null) {
            return (new Query\UsingJoin())->bind($left, $right, $kind, $node, $id, $node);
        }

        return $this->join($left, $right, $kind, $condition, $node, $id);
    }

    /**
     * Binds a SQLite table-list production and its preceding join.
     */
    public function sqlite(Node $node): BoundRelation
    {
        $prefix = Tree::child($node, ['stl_prefix']);
        $qualifier = Tree::child($node, ['on_using']);
        if ($prefix === null) {
            if ($qualifier !== null) {
                Tree::invalid($qualifier, 'ON without a join');
            }
            return $this->sqliteInput($node);
        }
        $previous = Tree::child($prefix, ['seltablist']);
        $operator = Tree::child($prefix, ['joinop']);
        if ($previous === null || $operator === null) {
            Tree::invalid($prefix, 'SQLite join');
        }
        $kind = $this->kind(Tree::text($operator), $operator);
        $id = $this->ids->join();
        $left = $this->relation($previous);
        $right = $this->sqliteInput($node);
        if (($qualifier !== null && str_starts_with(strtoupper(Tree::text($qualifier)), 'USING')) || str_contains(strtoupper(Tree::text($operator)), 'NATURAL')) {
            return (new Query\UsingJoin())->bind($left, $right, $kind, $node, $id, $qualifier);
        }
        $condition = $qualifier === null ? null : Tree::child($qualifier, ['expr']);

        return $this->join($left, $right, $kind, $condition, $node, $id);
    }


    /**
     * Binds a SQLite input independently of the preceding join-list production.
     */
    public function sqliteInput(Node $node): BoundRelation
    {
        $derived = Tree::child($node, ['select']);
        if ($derived !== null) {
            return $this->derived($node, $derived, Tree::child($node, ['as']));
        }
        $nested = Tree::child($node, ['seltablist']);
        if ($nested !== null) {
            return $this->relation($nested);
        }
        $name = Tree::child($node, ['nm']);
        if ($name === null) {
            Tree::invalid($node, 'SQLite relation');
        }
        if (Tree::child($node, ['exprlist']) !== null) {
            $children = array_values(array_filter($node->children, static fn ($child): bool => !$child instanceof Node || !in_array($child->name, ['stl_prefix', 'as', 'on_using'], true)));
            return (new RelationFactory())->function($node, new Node('table_function', 0, $children), $this->queries ?? new QueryContext($this->tables, $this->ids), $this->parent, $this->scopeId);
        }
        $parts = $this->tables->identifiers->parts($name);
        $db = Tree::child($node, ['dbnm']);
        if ($db !== null) {
            $parts = [$parts[0], ...$this->tables->identifiers->parts($db)];
        }
        return $this->table($node, $parts, Tree::child($node, ['as']));
    }

    /**
     * @param list<string> $parts
     */
    public function table(Node $source, array $parts, ?Node $aliasNode): BoundRelation
    {
        $alias = null;
        if ($aliasNode !== null && Tree::hasTokens($aliasNode)) {
            $tokens = $aliasNode->tokens();
            if (strtoupper($tokens[0]->text) === 'AS') {
                $tokens = array_slice($tokens, 1);
            }
            $alias = $this->tables->identifiers->name($tokens[0]);
        }
        $query = null;
        if (count($parts) === 1) {
            foreach ($this->queries->ctes ?? [] as $name => $candidate) {
                if ($this->tables->identifiers->equal($name, $parts[0])) {
                    $query = $candidate;
                    break;
                }
            }
        }
        $declaration = $query === null ? $this->tables->resolve($parts, $source) : QueryRelation::declaration($query, $parts[0], [], $source);
        $table = new TableUse($this->ids->relation(), $this->scopeId, $declaration, $alias, $source, $query);

        $relation = new BoundRelation($table, new Scope($this->tables->identifiers, [$table], parent: $this->parent, queries: $this->queries));
        return $aliasNode !== null && str_contains(Tree::text($aliasNode), '(') ? (new RelationFactory())->alias($relation, $aliasNode, $source, $this->queries ?? new QueryContext($this->tables, $this->ids), $this->scopeId) : $relation;
    }

    /**
     * Binds a derived table and exposes its ordered output declarations.
     */
    public function derived(Node $source, Node $node, ?Node $aliasNode): BoundRelation
    {
        $group = $this->grouped($source, $node, $aliasNode);
        if ($group !== null) {
            return $group;
        }
        $context = $this->queries ?? new QueryContext($this->tables, $this->ids);
        $query = $context->bind($node, $this->parent);
        $aliasParts = $aliasNode === null ? [] : $this->tables->identifiers->parts($aliasNode);
        $aliasParts = array_values(array_filter($aliasParts, static fn (string $name): bool => !in_array(strtoupper($name), ['AS', '(', ')', ','], true)));
        $alias = $aliasParts[0] ?? $query->scopeId;
        $declaration = QueryRelation::declaration($query, $alias, array_slice($aliasParts, 1), $source);
        $table = new TableUse($this->ids->relation(), $this->scopeId, $declaration, $alias, $source, $query);
        return new BoundRelation($table, new Scope($this->tables->identifiers, [$table], parent: $this->parent, queries: $context));
    }

    /**
     * Legacy derived-table productions also contain parenthesized relation lists.
     */
    public function grouped(Node $source, Node $node, ?Node $alias): ?BoundRelation
    {
        if ($node->name !== 'select_derived_union' || QueryNodes::setOperator($node) !== null) {
            return null;
        }
        $references = Tree::outer($node, ['table_ref']);
        if ($references === []) {
            return null;
        }
        $factor = Tree::child($references[0], ['table_factor']);
        if ($factor !== null && QueryNodes::isBody($factor)) {
            return null;
        }
        $relation = $this->bind($node);
        if ($relation === null || $alias === null || !Tree::hasTokens($alias)) {
            return $relation;
        }
        return (new RelationFactory())->alias($relation, $alias, $source, $this->queries ?? new QueryContext($this->tables, $this->ids), $this->scopeId);
    }

    /**
     * Resolves the logical operation from a join production.
     */
    public function kind(string $text, Node $source): JoinKind
    {
        $text = strtoupper($text);

        return match (true) {
            str_contains($text, 'LEFT') => JoinKind::Left,
            str_contains($text, 'RIGHT') => JoinKind::Right,
            str_contains($text, 'FULL') => JoinKind::Full,
            str_contains($text, 'CROSS'), $text === ',' => JoinKind::Cross,
            default => JoinKind::Inner,
        };
    }

    /**
     * Binds the match predicate before extending the nullable inputs.
     */
    public function join(BoundRelation $left, BoundRelation $right, JoinKind $kind, ?Node $condition, Node $source, string $id): BoundRelation
    {
        $scope = $left->scope->combine($right->scope, $source);
        $expression = $condition === null ? null : (new ExpressionBinder())->bind($condition, $scope);
        if ($expression !== null) {
            (new ExpressionRules($this->tables->identifiers->dialect, $this->tables->diagnostics))->predicate($expression);
        }
        $leftScope = in_array($kind, [JoinKind::Right, JoinKind::Full], true) ? $left->scope->extend($id) : $left->scope;
        $rightScope = in_array($kind, [JoinKind::Left, JoinKind::Full], true) ? $right->scope->extend($id) : $right->scope;

        return new BoundRelation(new Join($id, $kind, $left->relation, $right->relation, $expression, $source), $leftScope->combine($rightScope, $source));
    }
}
