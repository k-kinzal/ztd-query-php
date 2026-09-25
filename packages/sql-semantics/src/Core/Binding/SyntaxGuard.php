<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;

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
    public static function select(Node $statement, Node $select, \SqlSemantics\Core\Policy\SyntaxRules $syntax): void
    {
        Tree::assertChildren($select, $syntax->nodes('selectChildren'), ['SELECT']);
        $forbidden = $syntax->nodes('unsupportedModifier');
        foreach ($forbidden as $name) {
            foreach ($statement->find($name) as $node) {
                if ($node->tokens() !== []) {
                    Tree::unsupported($node, 'SELECT modifier');
                }
            }
        }
        foreach (Tree::outer($select, $syntax->nodes('selectOptions')) as $options) {
            $text = strtoupper(Tree::text($options));
            if (!in_array($text, ['', 'DISTINCT', 'ALL'], true)) {
                Tree::unsupported($options, 'SELECT options');
            }
        }
    }
}
