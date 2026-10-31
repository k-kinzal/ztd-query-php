<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Condition;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\BlockRule;
use SqlSemantics\Platform\MySql\Rules\Routine\BlockFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Statement\Identifier\Comparison;

/**
 * Resolves handler conditions as their list completes, before reading the handler body.
 *
 * Empty declaration lists open lexical scopes; completed blocks close them.
 * Only typed condition declarations and already handled values survive between
 * reductions. Ordinary block rules supply name resolution and duplicate identity.
 * No input tree or text escapes this transient lowering helper.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class HandlerPrefix
{
    /**
     * @var list<array{list<ConditionDeclaration>, list<Condition>}> Visible declarations and conditions already handled in each open block
     */
    public array $frames = [];

    /**
     * Tracks lexical declarations and reports the first refused handler condition.
     */
    public function observe(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name === 'sp_decls' && $node->children === []) {
            $this->frames[] = [$this->frames[(count($this->frames) - 1)][0] ?? [], []];
        } elseif ($node->name === 'sp_block_content') {
            array_pop($this->frames);
        }
        $index = array_key_last($this->frames);
        if ($index === null) {
            return null;
        }
        if ($node->name === 'sp_decl') {
            $form = $lowering->form($node);
            if ($form->signature === 'sp_decl: DECLARE_SYM ident CONDITION_SYM FOR_SYM sp_cond') {
                $declaration = (new BlockRule($lowering))->declaration($node);
                \SqlSemantics\Diagnostic\Check::invariant($declaration instanceof ConditionDeclaration, 'A completed condition declaration retains its declared meaning.');
                $this->frames[$index][0][] = $declaration;
            } elseif ($form->signature === 'sp_decl: DECLARE_SYM sp_handler_type HANDLER_SYM FOR_SYM sp_hcond_list sp_proc_stmt') {
                [$handled] = $this->conditions((new ConditionRule($lowering))->handled($form->node(4)), $lowering);
                $this->frames[$index][1] = $handled;
            }
        }
        if ($node->name !== 'sp_hcond_list') {
            return null;
        }
        [, $failure] = $this->conditions((new ConditionRule($lowering))->handled($node), $lowering);
        return $failure;
    }

    /**
     * Resolves a list against its lexical conditions without committing an incomplete handler.
     *
     * @param list<Condition> $conditions The completed prefix of this handler's condition list
     * @return array{list<Condition>, ?ParseFailure} Handled values and the first refusal
     */
    public function conditions(array $conditions, Lowering $lowering): array
    {
        [$declared, $handled] = $this->frames[count($this->frames) - 1] ?? [[], []];
        $derivation = new Derivation(new AnalysisContext($lowering->profile, [new \SqlSemantics\Statement\Identifier\Name(\SqlSemantics\Platform\MySql\Rules\SessionDatabase::UNNAMED)], complete: false, columnNames: Comparison::AsciiInsensitive));
        $scope = new ProgramScope($derivation->environment(), conditions: $declared);
        foreach ($conditions as $condition) {
            $handled = (new BlockFacts())->handled($handled, $condition, $derivation, $scope);
            foreach ($derivation->facts()->diagnostics as $problem) {
                return [$handled, new ParseFailure($problem, true)];
            }
        }
        return [$handled, null];
    }
}
