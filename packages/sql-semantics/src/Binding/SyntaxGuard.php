<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;

/**
 * Refuses semantic constructs that this implementation cannot account for.
 *
 * @visibility SqlSemantics
 */
final class SyntaxGuard
{
    /**
     * Rejects unmodeled clauses in a SELECT grammar production.
     */
    public static function select(Node $statement, Node $select): void
    {
        Tree::assertChildren($select, [
            'opt_target_list', 'target_list', 'distinct_clause', 'from_clause', 'where_clause',
            'select_options', 'select_item_list', 'opt_from_clause', 'opt_where_clause',
            'distinct', 'selcollist', 'from', 'where_opt', 'orderby_opt', 'limit_opt',
        ], ['SELECT']);
        $forbidden = ['opt_for_locking_clause', 'for_locking_clause', 'with_clause', 'with', 'into_clause', 'opt_into', 'locking_clause', 'opt_locking_clause', 'locking_clause_list', 'opt_procedure_analyse', 'opt_locking_clauses'];
        foreach ($forbidden as $name) {
            foreach ($statement->find($name) as $node) {
                if ($node->tokens() !== []) {
                    Tree::unsupported($node, 'SELECT modifier');
                }
            }
        }
        foreach (Tree::outer($select, ['distinct_clause', 'select_options', 'distinct']) as $options) {
            $text = strtoupper(Tree::text($options));
            if (!in_array($text, ['', 'DISTINCT', 'ALL'], true)) {
                Tree::unsupported($options, 'SELECT options');
            }
        }
    }
}
