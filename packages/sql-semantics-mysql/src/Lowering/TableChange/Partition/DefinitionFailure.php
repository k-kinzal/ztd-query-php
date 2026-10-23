<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Partition;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue;

/**
 * Locates a refused definition at the input boundary, before construction.
 *
 * Only the typed reason and an integer offset leave this parser-facing rule.
 * Locations follow native SQL observations on MySQL 5.6 through 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class DefinitionFailure
{
    /**
     * @param Lowering $lowering The current lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Builds the refusal at the bound or subpartition list that contradicted its role.
     */
    public function failure(DefinitionIssue $issue, Node $definition, int $start, ?int $expected): RejectedInput
    {
        $form = $this->lowering->form($definition);
        if ($issue !== DefinitionIssue::SubpartitionCount) {
            return new RejectedInput($issue, $start);
        }
        $sub = $this->lowering->form($form->node(4));
        $range = (new SourceRanges())->span($definition);
        if ($sub->signature === 'opt_sub_partition:') {
            return new RejectedInput($issue, $range[1] ?? $start);
        }
        $tokens = $sub->node->tokens();
        $legacy = in_array($this->lowering->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $offset = $expected === 0 ? ($tokens[1]->offset ?? $start) : ($legacy ? $tokens[count($tokens) - 1]->offset : $tokens[0]->offset);
        return new RejectedInput($issue, $offset);
    }
}
