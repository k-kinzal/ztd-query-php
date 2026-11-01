<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

/**
 * Resolves completed label operands before a later lexical or syntax refusal.
 * Only decoded labels and handler scope identities survive between reductions.
 * Observed through SQL on MySQL 5.7.44 and 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LabelPrefix
{
    /**
 * @var array<string, bool> Visible labels and whether they name loops
 */
    private array $labels = [];

    /**
 * @var list<array{int, array<string, bool>}> Handler boundaries and saved outer labels
 */
    private array $handlers = [];

    /**
     * Tracks label and handler scopes, then resolves the completed jump or end label.
     *
     * @param list<Token> $tokens Transient tokens identifying the construct after a colon
     */
    public function observe(Node $node, Token $lookahead, Lowering $lowering, array $tokens): ?ParseFailure
    {
        $this->handler($node);
        if ($node->name === 'label_ident' && $lookahead->text === ':') {
            $name = $lowering->names->identifier($node)->value;
            $key = mb_strtolower($name, 'UTF-8');
            if (isset($this->labels[$key])) {
                return new ParseFailure(new ProgramProblem(ProgramRule::RedefinedLabel, $name), true);
            }
            $following = array_values(array_filter($tokens, static fn (Token $token): bool => $token->offset > $lookahead->offset));
            $this->labels[$key] = in_array($following[0]->name ?? '', ['LOOP_SYM', 'WHILE', 'REPEAT_SYM'], true);
        }
        if (in_array($node->name, ['sp_proc_stmt_leave', 'sp_proc_stmt_iterate'], true)) {
            $name = $lowering->names->identifier($lowering->form($node)->node(1))->value;
            $loop = $this->labels[mb_strtolower($name, 'UTF-8')] ?? null;
            $iterate = $node->name === 'sp_proc_stmt_iterate';
            if ($loop === null || ($iterate && !$loop)) {
                return new ParseFailure(new ProgramProblem($iterate ? ProgramRule::IterateWithoutLabel : ProgramRule::LeaveWithoutLabel, $name), true);
            }
        }
        return $this->closing($node, $lowering);
    }

    /**
     * A handler owns a new label scope even when its condition list reduces repeatedly.
     */
    public function handler(Node $node): void
    {
        if ($node->name === 'sp_hcond_list') {
            $start = $node->tokens()[0]->offset;
            if (($this->handlers[count($this->handlers) - 1][0] ?? null) !== $start) {
                $this->handlers[] = [$start, $this->labels];
                $this->labels = [];
            }
        } elseif ($node->name === 'sp_decl' && $node->find('sp_handler_type') !== []) {
            $this->labels = array_pop($this->handlers)[1] ?? [];
        }
    }

    /**
     * Ends a labeled construct without exporting its label to later siblings.
     */
    public function closing(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (!in_array($node->name, ['sp_labeled_block', 'sp_labeled_control'], true)) {
            return null;
        }
        $form = $lowering->form($node);
        $name = $lowering->names->identifier($form->node(0))->value;
        unset($this->labels[mb_strtolower($name, 'UTF-8')]);
        $end = $form->node(3);
        if ($end->children === []) {
            return null;
        }
        $last = $lowering->names->identifier($lowering->form($end)->node(0))->value;
        return mb_strtolower($name, 'UTF-8') === mb_strtolower($last, 'UTF-8') ? null : new ParseFailure(new ProgramProblem(ProgramRule::EndLabelMismatch, $last), true);
    }
}
