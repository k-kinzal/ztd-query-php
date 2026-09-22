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
            Dialect::Sqlite => ['input', 'ecmd'],
        };
        if ($dialect === Dialect::MySql && $tree->name === 'query') {
            $root = 'query';
            $statement = 'statement';
        }
        if ($tree->name !== $root) {
            throw new SemanticException('dialect-mismatch', 'Expected a ' . $dialect->value . ' parser root.', $tree);
        }
        $statements = array_values(array_filter(Tree::outer($tree, [$statement]), static fn (Node $node): bool => Tree::hasTokens($node)));
        if ($dialect === Dialect::Sqlite) {
            $statements = array_map(static fn (Node $node): Node => Tree::child($node, ['explain']) === null ? (Tree::outer($node, ['cmd'])[0] ?? $node) : $node, $statements);
        }
        if ($statements === []) {
            $statements = Tree::outer($tree, match ($dialect) {
                Dialect::PostgreSql => ['TransactionStmtLegacy'], Dialect::MySql => ['simple_statement_or_begin', 'begin'], Dialect::Sqlite => ['cmd']
            });
        }
        if ($statements === []) {
            Tree::invalid($tree, 'empty statement list');
        }

        return $statements;
    }
}
