<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use Closure;
use SqlParser\Lexer\Token;
use SqlParser\Table\TableRule;

/**
 * Builds visible reduction nodes and dispatches the optional parse observer.
 *
 * @visibility root
 */
final class Reductions
{
    /**
     * @param (Closure(Node, Token): void)|null $observer The optional reduction observer
     */
    public function __construct(private readonly ?Closure $observer = null)
    {
    }

    /**
     * Builds a visible production and reports it before its parent is read.
     *
     * @param list<Node|Token> $children The completed production operands
     */
    public function node(string $name, TableRule $rule, array $children, Token $lookahead): ?Node
    {
        if ($rule->hidden) {
            return null;
        }
        $node = new Node($name, $rule->ordinal, $children);
        if ($this->observer !== null) {
            ($this->observer)($node, $lookahead);
        }

        return $node;
    }
}
