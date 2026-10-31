<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Set;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Retains NAMES assignment conditions when a later input error prevents publication.
 *
 * MySQL 5.6 stops at that item; later releases finish the assignment
 * list first. Earlier variable lookups and lexical program scopes use the
 * ordinary semantic rules. Complete statements retain their expressions as NamesAssignment values.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxNames
{
    private ?ParseFailure $failure = null;
    private bool $names = false;

    /**
     * @param InputLocals $locals The transient lexical scope of the input being read
     */
    public function __construct(public readonly InputLocals $locals = new InputLocals())
    {
    }

    /**
     * Recognizes the refused assignment and its exact operand positions.
     */
    public function assignment(Node $node, Lowering $lowering, ?InputLocals $locals = null): ?RejectedInput
    {
        if ($node->name !== 'option_value_no_option_type' || $lowering->form($node)->signature !== 'option_value_no_option_type: NAMES_SYM equal expr') {
            return null;
        }
        $form = $lowering->form($node);
        $offset = (new SourceRanges())->span($form->node(1))[0] ?? 0;
        $end = (new SourceRanges())->span($form->node(2))[1] ?? $offset;
        $legacy = $lowering->profile->grammar === GrammarRelease::MySql5651;

        return new RejectedInput(new RefusedNamesAssignment($offset, $end, $locals?->has(new Name('names')) ?? false), $legacy ? $end : $offset, $legacy);
    }

    /**
     * Retains completion order while reading a whole statement or its completed prefix.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        $this->locals->observe($node, $lowering);
        if ($node->name === 'set') {
            $failure = $this->names ? $this->failure : null;
            $this->failure = null;
            $this->names = false;
            return $failure;
        }
        $refusal = $this->assignment($node, $lowering, $this->locals);
        if ($refusal !== null) {
            $this->names = true;
            $this->failure ??= new ParseFailure($refusal->problem, true, $refusal->aborts ? NoticePhase::Syntax : NoticePhase::Contextualization);
            return $refusal->aborts ? $this->failure : null;
        }
        if ($this->failure === null && in_array($node->name, ['option_value_no_option_type', 'option_value_following_option_type'], true)) {
            $this->failure = $this->preceding($node, $lowering, $this->locals);
        }

        return null;
    }

    /**
     * Finds a preceding variable-name lookup without executing any assignment.
     */
    public function preceding(Node $node, Lowering $lowering, InputLocals $locals): ?ParseFailure
    {
        try {
            $rule = new ItemRule($lowering);
            $item = $node->name === 'option_value_no_option_type' ? $rule->unscoped($lowering->form($node)) : $rule->named(null, $lowering->form($node));
        } catch (RejectedInput) {
            // The operand's own completed-production rule retains this earlier refusal.
            return null;
        }
        if ($item instanceof NameAssignment && $item->qualifier === null && $locals->has($item->name)) {
            return null;
        }
        $derivation = new Derivation(new AnalysisContext($lowering->profile, [new Name(\SqlSemantics\Platform\MySql\Rules\SessionDatabase::UNNAMED)], complete: false));
        $item->deriveItem($derivation);
        foreach ($derivation->facts()->diagnostics as $problem) {
            if ($problem instanceof UnknownSystemVariable) {
                return new ParseFailure($problem, true);
            }
        }

        return null;
    }

    /**
     * Reads enclosing variable declarations in reduction order for a complete parsed input.
     */
    public function scoped(Node $tree, RefusedNamesAssignment $problem, Lowering $lowering): RefusedNamesAssignment
    {
        $locals = new InputLocals();
        $pending = [$tree];
        $visited = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($visited[spl_object_id($node)])) {
                $locals->observe($node, $lowering);
                $refusal = $this->assignment($node, $lowering, $locals);
                if ($refusal?->problem instanceof RefusedNamesAssignment && $refusal->problem->offset === $problem->offset) {
                    return $refusal->problem;
                }
                continue;
            }
            $visited[spl_object_id($node)] = true;
            $pending[] = $node;
            foreach (array_reverse($node->children) as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return $problem;
    }
}
