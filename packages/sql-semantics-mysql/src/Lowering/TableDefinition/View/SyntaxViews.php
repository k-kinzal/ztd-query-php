<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\View;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause;

/**
 * Interprets the command role of the shared view existence clause.
 *
 * A completed name and column list identify a view header even when its body is
 * incomplete. Temporary tokens distinguish ALTER and CREATE OR REPLACE from
 * ordinary CREATE. No syntax object is retained in the resulting diagnostic.
 * Observed through SQL on MySQL 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxViews
{
    private ?RejectedInput $candidate = null;

    /**
     * Refuses an incompatible clause after its name and optional column list complete.
     *
     * @param list<Token> $tokens Temporary tokens of the input
     */
    public function starting(Node $node, Lowering $lowering, array $tokens): ?RejectedInput
    {
        if ($lowering->profile->grammar !== GrammarRelease::MySql910) {
            return null;
        }
        if ($node->name === 'table_ident') {
            $this->candidate = $this->named($node, $tokens);
        }
        if ($node->name !== 'opt_derived_column_list' || $this->candidate === null) {
            return null;
        }
        $columns = $node->tokens();
        return new RejectedInput($this->candidate->problem, $columns === [] ? $this->candidate->end : $columns[count($columns) - 1]->end());
    }

    /**
     * Recognizes an incompatible command prefix immediately before a view name.
     *
     * @param list<Token> $tokens Temporary tokens of the input
     */
    public function named(Node $name, array $tokens): ?RejectedInput
    {
        $named = $name->tokens();
        $before = array_values(array_filter($tokens, static fn (Token $token): bool => $token->offset < $named[0]->offset));
        $header = array_slice($before, -4);
        if (array_column($header, 'name') !== ['VIEW_SYM', 'IF', 'NOT_SYM', 'EXISTS']) {
            return null;
        }
        $alter = false;
        $replace = false;
        $previous = null;
        foreach ($before as $token) {
            if (in_array($token->name, ['ALTER', 'CREATE'], true)) {
                $alter = $token->name === 'ALTER';
                $replace = false;
            } elseif ($token->name === 'REPLACE_SYM' && $previous === 'OR_SYM') {
                $replace = true;
            }
            $previous = $token->name;
        }
        return $alter || $replace ? new RejectedInput(new RefusedExistenceClause($header[1]->offset), $named[count($named) - 1]->end()) : null;
    }

    /**
     * Interprets a complete shared view tail in its surrounding command.
     *
     * @param list<Token> $tokens Temporary tokens of the complete input
     */
    public function rejected(Node $node, Lowering $lowering, array $tokens): ?RejectedInput
    {
        if ($node->name !== 'view_tail') {
            return null;
        }
        $form = $lowering->form($node);
        return $form->signature === 'view_tail: view_suid VIEW_SYM opt_if_not_exists table_ident opt_derived_column_list AS view_query_block'
            && $this->named($form->node(3), $tokens) !== null ? $this->tail($node, $lowering) : null;
    }

    /**
     * Checks a prohibited existence clause before ordinary view-body lowering.
     */
    public function tail(Node $tail, Lowering $lowering): ?RejectedInput
    {
        $form = $lowering->form($tail);
        if ($form->signature !== 'view_tail: view_suid VIEW_SYM opt_if_not_exists table_ident opt_derived_column_list AS view_query_block') {
            return null;
        }
        $first = $form->node(2)->tokens()[0] ?? null;
        $header = [...$form->node(3)->tokens(), ...$form->node(4)->tokens()];
        return $first === null ? null : new RejectedInput(new RefusedExistenceClause($first->offset), $header[count($header) - 1]->end());
    }
}
