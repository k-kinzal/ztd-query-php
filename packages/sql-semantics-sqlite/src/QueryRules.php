<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Binding\BoundRelation;
use SqlSemantics\Core\Binding\FromBinder;
use SqlSemantics\Core\Policy\QueryRules as Contract;

/**
 * Sqlite QueryRules implementation.
 *
 * @visibility SqlSemantics
 */
final class QueryRules implements Contract
{
    /**
     * @return list<Node>
     */
    public function projectionItems(Node $select): array
    {
        return array_reverse($select->find('selcollist'));
    }

    /**
     * @return list<Token>
     */
    public function projectionTokens(Node $item, ?Node $expression): array
    {
        if ($expression !== null) {
            return $expression->tokens();
        }
        $tokens = [];
        foreach (Tree::significant($item) as $child) {
            if ($child instanceof Node && in_array($child->name, ['sclp', 'as'], true)) {
                continue;
            }
            array_push($tokens, ...$child instanceof Node ? $child->tokens() : [$child]);
        }
        return $tokens;
    }

    /**
     * @return list<Node>
     */
    public function orderingNodes(Node $statement): array
    {
        $order = Tree::outer($statement, ['orderby_opt'])[0] ?? null;
        return $order === null ? [] : array_reverse($order->find('sortlist'));
    }

    /**
     * Interprets one table reference or join using the core relation binder.
     */
    public function relation(Node $node, FromBinder $binder): BoundRelation
    {
        $name = Tree::child($node, ['nm']);
        if ($name === null) {
            Tree::unsupported($node, 'Sqlite relation');
        }
        Tree::assertChildren($node, ['stl_prefix', 'nm', 'dbnm', 'as', 'on_using'], []);
        $parts = $binder->tables->identifiers->parts($name);
        $db = Tree::child($node, ['dbnm']);
        if ($db !== null) {
            $parts = [$parts[0], ...$binder->tables->identifiers->parts($db)];
        }
        $prefix = Tree::child($node, ['stl_prefix']);
        $qualifier = Tree::child($node, ['on_using']);
        if ($prefix === null) {
            if ($qualifier !== null) {
                Tree::unsupported($qualifier, 'ON without a join');
            }
            return $binder->table($node, $parts, Tree::child($node, ['as']));
        }
        $previous = Tree::child($prefix, ['seltablist']);
        $operator = Tree::child($prefix, ['joinop']);
        if ($previous === null || $operator === null) {
            Tree::unsupported($prefix, 'Sqlite join');
        }
        $kind = $binder->kind(Tree::text($operator), $operator);
        $id = $binder->ids->join();
        $left = $binder->relation($previous);
        $right = $binder->table($node, $parts, Tree::child($node, ['as']));
        if ($qualifier !== null && strtoupper($qualifier->tokens()[0]->text) !== 'ON') {
            Tree::unsupported($qualifier, 'join qualification');
        }
        $condition = $qualifier === null ? null : Tree::child($qualifier, ['expr']);
        return $binder->join($left, $right, $kind, $condition, $node, $id);
    }
}
