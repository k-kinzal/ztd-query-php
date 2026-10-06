<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Attach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Detach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\NameOperand;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * Lowers ATTACH and DETACH.
 *
 * Rule: SQLITE-CONNECTION-LOWER-001. Scope: the `cmd` productions of ATTACH
 * and DETACH, with database_kw_opt and key_opt. Constructors: Attach, Detach,
 * NameOperand. An operand that is one identifier inside any number of
 * parentheses lowers to a NameOperand inside as many groupings, because SQLite
 * reads it as text; every other operand is an ordinary expression. The
 * DATABASE keyword is declared noise. Terminates: the parentheses around an
 * operand are counted in a loop over a strict subtree chain.
 * Source: https://sqlite.org/lang_attach.html, https://sqlite.org/lang_detach.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ConnectionRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers ATTACH or DETACH, or answers null for a command of another family.
     */
    public function command(Form $form): ?Statement
    {
        return match ($form->signature) {
            'cmd: ATTACH database_kw_opt expr AS expr key_opt' => new Attach(
                $this->operand($this->keyword($form->node(1), $form->node(2))),
                $this->operand($form->node(4)),
                $this->key($form->node(5)),
            ),
            'cmd: DETACH database_kw_opt expr' => new Detach($this->operand($this->keyword($form->node(1), $form->node(2)))),
            default => null,
        };
    }

    /**
     * Checks the optional DATABASE keyword and answers the operand that follows it.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function keyword(Node $keyword, Node $operand): Node
    {
        $form = $this->lowering->productions->form($keyword);

        return match ($form->signature) {
            'database_kw_opt:', 'database_kw_opt: DATABASE' => $operand,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional key expression.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function key(Node $key): ?Scalar
    {
        $form = $this->lowering->productions->form($key);

        return match ($form->signature) {
            'key_opt:' => null,
            'key_opt: KEY expr' => $this->operand($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an operand: an identifier is the text of its name, anything else an expression.
     */
    public function operand(Node $expression): Scalar
    {
        $depth = 0;
        $form = $this->lowering->productions->form($expression);
        while ($form->signature === 'expr: LP expr RP') {
            $depth++;
            $form = $this->lowering->productions->form($form->node(1));
        }
        if ($form->signature !== 'expr: idj') {
            return $this->lowering->expressions->expression($expression);
        }
        $operand = new NameOperand($this->lowering->names->token($form->token(0)));
        for ($level = 0; $level < $depth; $level++) {
            $operand = new Grouped($operand);
        }

        return $operand;
    }
}
