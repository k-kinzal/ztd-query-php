<?php

declare(strict_types=1);

namespace SqlSemantics\Lowering;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;

/**
 * A typed, temporary view of one grammar production of a parse tree.
 *
 * The signature spells the production as the grammar writes it. A rule that
 * dispatches on signatures applies to every grammar release that has the
 * production.
 *
 * @visibility SqlSemantics
 */
final class Form
{
    /**
     * @param Node $node The parse tree node
     * @param string $signature The production the node matched, such as `where_opt: WHERE expr` or `where_opt:`
     */
    public function __construct(public readonly Node $node, public readonly string $signature)
    {
    }

    /**
     * Answers the nonterminal child at a position.
     */
    public function node(int $position): Node
    {
        $child = $this->node->children[$position] ?? null;
        Check::invariant($child instanceof Node, 'The production has no nonterminal at position ' . $position . ': ' . $this->signature);

        return $child;
    }

    /**
     * Answers the terminal child at a position.
     */
    public function token(int $position): Token
    {
        $child = $this->node->children[$position] ?? null;
        Check::invariant($child instanceof Token, 'The production has no terminal at position ' . $position . ': ' . $this->signature);

        return $child;
    }
}
