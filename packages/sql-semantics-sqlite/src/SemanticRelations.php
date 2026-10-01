<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\RelationReader;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\RelationRules as Contract;
use SqlSemantics\Semantic\Relation\Join;
use SqlSemantics\Semantic\Relation\TableReference;

/**
 * Lowers relation occurrences and joins.
 * @visibility SqlSemantics
 */
final class SemanticRelations implements Contract
{
    /**
     * Lowers one named table or joined relation while preserving occurrence identity.
     */
    public function relation(Node $node, RelationReader $binder): TableReference|Join
    {
        $name = Tree::child($node, ['nm']);
        if ($name === null) {
            Tree::assertChildren($node, ['seltablist'], ['(', ')']);
            $nested = Tree::child($node, ['seltablist']);
            if ($nested === null) {
                Tree::unsupported($node, 'Sqlite relation');
            }
            return $binder->relation($nested);
        }
        Tree::assertChildren($node, ['stl_prefix', 'nm', 'dbnm', 'as', 'on_using'], []);
        $parts = $binder->names->parts($name);
        $db = Tree::child($node, ['dbnm']);
        if ($db !== null) {
            $parts = [$parts[0], ...$binder->names->parts($db)];
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
        $left = $binder->relation($previous);
        $right = $binder->table($node, $parts, Tree::child($node, ['as']));
        if ($qualifier !== null && strtoupper($qualifier->tokens()[0]->text) !== 'ON') {
            Tree::unsupported($qualifier, 'join qualification');
        }
        $condition = $qualifier === null ? null : Tree::child($qualifier, ['expr']);
        return $binder->join($left, $right, $kind, $condition, $node);
    }
}
