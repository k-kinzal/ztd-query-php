<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\SemanticException;

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
        [$root, $statement] = $dialect->platform()->statementNames();
        if ($tree->name !== $root && !in_array($tree->name, $dialect->platform()->syntax()->nodes('statementRoot'), true)) {
            throw new SemanticException('dialect-mismatch', 'Expected a ' . $dialect->value . ' parser root.', $tree);
        }
        $statements = array_values(array_filter(Tree::outer($tree, [$statement, ...$dialect->platform()->syntax()->nodes('statement')]), static fn (Node $node): bool => $node->tokens() !== []));
        if ($statements === []) {
            Tree::unsupported($tree, 'empty statement list');
        }

        return $statements;
    }
}
