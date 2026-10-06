<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Set;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the value of a variable assignment in SET.
 *
 * Rule: MYSQL-SET-VALUE-LOWERING-001. Scope: set_expr_or_default. A value
 * is an expression or one of the keywords DEFAULT, ON, ALL, BINARY, ROW and
 * SYSTEM (SetWord). An expression that is nothing but a column name, through
 * the unit productions from `expr` down to `simple_ident`, is the bare name
 * whose text a system variable receives (BareName, MYSQL-SET-WORD-001);
 * every other expression is lowered by the expression family. Terminates:
 * the descent follows unit productions of a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class ValueRule
{
    /**
     * The keyword values by production.
     */
    private const WORDS = [
        'set_expr_or_default: DEFAULT' => SetWord::Default, 'set_expr_or_default: DEFAULT_SYM' => SetWord::Default,
        'set_expr_or_default: ON' => SetWord::On, 'set_expr_or_default: ON_SYM' => SetWord::On, 'set_expr_or_default: ALL' => SetWord::All,
        'set_expr_or_default: BINARY' => SetWord::Binary, 'set_expr_or_default: BINARY_SYM' => SetWord::Binary,
        'set_expr_or_default: ROW_SYM' => SetWord::Row, 'set_expr_or_default: SYSTEM_SYM' => SetWord::System,
    ];

    /**
     * The rules an expression that is only a name passes through on its way to `simple_ident`.
     */
    private const UNITS = ['expr' => true, 'bool_pri' => true, 'predicate' => true, 'bit_expr' => true, 'simple_expr' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `set_expr_or_default`.
     *
     * @param bool $system Whether the assigned variable is known to be a system variable
     * @throws ImplementationGap When a production has no rule
     */
    public function value(Node $value, bool $system): Scalar|SetWord
    {
        $form = $this->lowering->form($value);
        if ($form->signature !== 'set_expr_or_default: expr') {
            return self::WORDS[$form->signature] ?? throw ImplementationGap::production($form);
        }

        return $this->word($form->node(0), $system) ?? $this->lowering->expressions->expression($form->node(0));
    }

    /**
     * Lowers an expression that is nothing but a column name into a word; any other expression is null.
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
