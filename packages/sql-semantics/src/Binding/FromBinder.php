<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
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
    public function __construct(public readonly TableResolver $tables, public readonly IdentitySequence $ids)
    {
    }

    /**
     * Builds the FROM tree in SQL binding order.
     */
    public function bind(Node $from): ?BoundRelation
    {
        $nodes = Tree::outer($from, ['table_ref', 'table_reference', 'seltablist']);
        $result = null;
        foreach ($nodes as $node) {
            $right = $this->relation($node);
            $result = $result === null ? $right : $this->join($result, $right, JoinKind::Cross, null, $from, $this->ids->join());
        }
        if ($result === null && $from->tokens() !== []) {
            Tree::unsupported($from, 'FROM clause');
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
        $joins = Tree::outer($node, ['joined_table']);
        if ($joins !== []) {
            if (Tree::child($node, ['alias_clause', 'opt_alias_clause']) !== null) {
                Tree::unsupported($node, 'joined-table alias');
            }
            return $this->joined($joins[0]);
        }
        $base = Tree::outer($node, ['relation_expr', 'single_table'])[0] ?? null;
        if ($base === null) {
            Tree::unsupported($node, 'relation');
        }
        $name = Tree::child($base, ['qualified_name', 'table_ident']);
        if ($name === null) {
            Tree::unsupported($base, 'table reference');
        }
        $allowed = ['relation_expr', 'qualified_name', 'opt_alias_clause', 'single_table', 'table_ident', 'opt_table_alias'];
        Tree::assertChildren($base, $allowed, []);
        $alias = Tree::outer($node, ['opt_alias_clause', 'opt_table_alias'])[0] ?? null;

        return $this->table($node, $this->tables->identifiers->parts($name), $alias);
    }

    /**
     * Binds a PostgreSQL or MySQL joined-table grammar node.
     */
    public function joined(Node $node): BoundRelation
    {
        $references = Tree::outer($node, ['table_ref', 'table_reference']);
        if (count($references) !== 2) {
            Tree::unsupported($node, 'join operands');
        }
        $kindNode = Tree::child($node, ['join_type', 'outer_join_type', 'inner_join_type', 'natural_join_type']);
        $kind = $kindNode === null ? JoinKind::Inner : $this->kind(Tree::text($kindNode), $node);
        $qualifier = Tree::child($node, ['join_qual']);
        if ($qualifier !== null && strtoupper($qualifier->tokens()[0]->text) !== 'ON') {
            Tree::unsupported($qualifier, 'join qualification');
        }
        $condition = $qualifier === null ? Tree::child($node, ['expr']) : (Tree::outer($qualifier, ['a_expr'])[0] ?? null);
        if ($kindNode === null && in_array('CROSS', array_map(static fn ($token): string => strtoupper($token->text), $node->tokens()), true)) {
            $kind = JoinKind::Cross;
        }
        $id = $this->ids->join();
        $left = $this->relation($references[0]);
        $right = $this->relation($references[1]);
        if ($condition === null && $kind !== JoinKind::Cross) {
            Tree::unsupported($node, 'join without an ON condition');
        }

        return $this->join($left, $right, $kind, $condition, $node, $id);
    }

    /**
     * Binds a SQLite table-list production and its preceding join.
     */
    public function sqlite(Node $node): BoundRelation
    {
        $name = Tree::child($node, ['nm']);
        if ($name === null) {
            Tree::unsupported($node, 'SQLite relation');
        }
        Tree::assertChildren($node, ['stl_prefix', 'nm', 'dbnm', 'as', 'on_using'], []);
        $parts = $this->tables->identifiers->parts($name);
        $db = Tree::child($node, ['dbnm']);
        if ($db !== null) {
            $parts = [$parts[0], ...$this->tables->identifiers->parts($db)];
        }

        $prefix = Tree::child($node, ['stl_prefix']);
        $qualifier = Tree::child($node, ['on_using']);
        if ($prefix === null) {
            if ($qualifier !== null) {
                Tree::unsupported($qualifier, 'ON without a join');
            }
            return $this->table($node, $parts, Tree::child($node, ['as']));
        }
        $previous = Tree::child($prefix, ['seltablist']);
        $operator = Tree::child($prefix, ['joinop']);
        if ($previous === null || $operator === null) {
            Tree::unsupported($prefix, 'SQLite join');
        }
        $kind = $this->kind(Tree::text($operator), $operator);
        $id = $this->ids->join();
        $left = $this->relation($previous);
        $right = $this->table($node, $parts, Tree::child($node, ['as']));
        if ($qualifier !== null && strtoupper($qualifier->tokens()[0]->text) !== 'ON') {
            Tree::unsupported($qualifier, 'join qualification');
        }
        $condition = $qualifier === null ? null : Tree::child($qualifier, ['expr']);

        return $this->join($left, $right, $kind, $condition, $node, $id);
    }

    /**
     * @param list<string> $parts
     */
    public function table(Node $source, array $parts, ?Node $aliasNode): BoundRelation
    {
        $alias = null;
        if ($aliasNode !== null && $aliasNode->tokens() !== []) {
            $tokens = $aliasNode->tokens();
            if (strtoupper($tokens[0]->text) === 'AS') {
                $tokens = array_slice($tokens, 1);
            }
            if (count($tokens) !== 1) {
                Tree::unsupported($aliasNode, 'table alias');
            }
            $alias = $this->tables->identifiers->name($tokens[0]);
        }
        $table = new TableUse($this->ids->relation(), 's0', $this->tables->resolve($parts, $source), $alias, $source);

        return new BoundRelation($table, new Scope($this->tables->identifiers, [$table]));
    }

    /**
     * Resolves the logical operation from a join production.
     */
    public function kind(string $text, Node $source): JoinKind
    {
        $text = strtoupper($text);
        if (str_contains($text, 'NATURAL') || str_contains($text, 'STRAIGHT')) {
            Tree::unsupported($source, 'join operation');
        }

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
            (new ExpressionRules($this->tables->identifiers->dialect))->predicate($expression);
        }
        $leftScope = in_array($kind, [JoinKind::Right, JoinKind::Full], true) ? $left->scope->extend($id) : $left->scope;
        $rightScope = in_array($kind, [JoinKind::Left, JoinKind::Full], true) ? $right->scope->extend($id) : $right->scope;

        return new BoundRelation(new Join($id, $kind, $left->relation, $right->relation, $expression, $source), $leftScope->combine($rightScope, $source));
    }
}
