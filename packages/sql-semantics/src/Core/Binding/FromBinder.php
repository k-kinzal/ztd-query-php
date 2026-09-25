<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Model\Join;
use SqlSemantics\Core\Model\JoinKind;
use SqlSemantics\Core\Model\TableUse;

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
        $nodes = Tree::outer($from, $this->tables->identifiers->dialect->platform()->syntax()->nodes('relation'));
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
        return $this->tables->identifiers->dialect->platform()->query()->relation($node, $this);
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
