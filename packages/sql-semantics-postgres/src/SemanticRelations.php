<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\RelationReader;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Model\JoinKind;
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
        $joins = Tree::outer($node, ['joined_table']);
        if ($joins !== []) {
            if (Tree::child($node, ['alias_clause', 'opt_alias_clause']) !== null) {
                Tree::unsupported($node, 'joined-table alias');
            }
            return $this->joined($joins[0], $binder);
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
        return $binder->table($node, $binder->names->parts($name), $alias);
    }

    /**
     * Binds a PostgreSql joined-table grammar node.
     */
    public function joined(Node $node, RelationReader $binder): TableReference|Join
    {
        $children = Tree::significant($node);
        if (count($children) === 3 && Tree::text($children[0]) === '(' && Tree::text($children[2]) === ')' && $children[1] instanceof Node) {
            return $binder->relation($children[1]);
        }
        $references = Tree::outer($node, ['table_ref', 'table_reference']);
        if (count($references) !== 2) {
            Tree::unsupported($node, 'join operands');
        }
        $kindNode = Tree::child($node, ['join_type', 'outer_join_type', 'inner_join_type', 'natural_join_type']);
        $kind = $kindNode === null ? JoinKind::Inner : $binder->kind(Tree::text($kindNode), $node);
        $qualifier = Tree::child($node, ['join_qual']);
        if ($qualifier !== null && strtoupper($qualifier->tokens()[0]->text) !== 'ON') {
            Tree::unsupported($qualifier, 'join qualification');
        }
        $condition = $qualifier === null ? Tree::child($node, ['expr']) : Tree::outer($qualifier, ['a_expr'])[0] ?? null;
        if ($kindNode === null && in_array('CROSS', array_map(static fn ($token): string => strtoupper($token->text), $node->tokens()), true)) {
            $kind = JoinKind::Cross;
        }
        $left = $binder->relation($references[0]);
        $right = $binder->relation($references[1]);
        if ($condition === null && $kind !== JoinKind::Cross) {
            Tree::unsupported($node, 'join without an ON condition');
        }
        return $binder->join($left, $right, $kind, $condition, $node);
    }
}
