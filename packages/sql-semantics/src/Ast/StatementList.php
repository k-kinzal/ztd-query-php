<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;

/**
 * Validates the parser dialect and preserves statement boundaries.
 *
 * @visibility SqlSemantics
 */
final class StatementList
{
    /**
     * @return list<Node>
     * @throws SemanticException
     */
    public static function read(Node $tree, Dialect $dialect): array
    {
        [$root, $statement] = match ($dialect) {
            Dialect::PostgreSql => ['parse_toplevel', 'stmt'],
            Dialect::MySql => ['start_entry', 'simple_statement'],
            Dialect::Sqlite => ['input', 'cmd'],
        };
        if ($tree->name !== $root) {
            throw new SemanticException('dialect-mismatch', 'Expected a ' . $dialect->value . ' parser root.', $tree);
        }
        $statements = array_values(array_filter(Tree::outer($tree, [$statement]), static fn (Node $node): bool => $node->tokens() !== []));
        if ($statements === []) {
            Tree::unsupported($tree, 'empty statement list');
        }

        return $statements;
    }
}
