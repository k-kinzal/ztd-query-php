<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Reads a standalone identifier as a word assigned to a server variable.
 *
 * SET and INSTALL COMPONENT share this meaning; a name inside a larger
 * expression still lowers as a column reference. No column occurrence is
 * constructed or discarded for the word role.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering
 */
final class WordRule
{
    private const UNITS = ['expr' => true, 'bool_pri' => true, 'predicate' => true, 'bit_expr' => true, 'simple_expr' => true];

    /**
     * @param Lowering $lowering The statement's lowering context
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Answers the word, or null when the expression includes an operation.
     */
    public function word(Node $expression, bool $system): ?BareName
    {
        $node = $expression;
        while (isset(self::UNITS[$node->name]) && count($node->children) === 1 && $node->children[0] instanceof Node) {
            $node = $node->children[0];
        }
        if ($node->name !== 'simple_ident' || count($node->children) !== 1 || !$node->children[0] instanceof Node) {
            return null;
        }
        $idents = [];
        foreach ($node->children[0]->name === 'ident' ? [$node->children[0]] : $node->children[0]->children as $child) {
            if ($child instanceof Node) {
                $idents[] = $child;
            }
        }
        if ($idents === [] || count($idents) > 3 || array_filter($idents, static fn (Node $ident): bool => $ident->name !== 'ident') !== []) {
            return null;
        }
        $parts = array_map(fn (Node $ident): \SqlSemantics\Statement\Identifier\Name => $this->lowering->names->identifier($ident), $idents);
        $word = array_pop($parts);
        $qualifier = match (count($parts)) {
            0 => null,
            1 => new QualifiedName($parts[0]),
            default => new QualifiedName($parts[1], $parts[0]),
        };

        return new BareName($word, $qualifier, $system);
    }
}
