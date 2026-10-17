<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Lowering;

/**
 * Refuses table-identity operands while preserving aliases with empty names.
 * Table identities and open handler references use this rule. In particular,
 * a multiple-DELETE target can name an empty alias through table_ident_opt_wild.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxTableNames
{
    /**
     * Answers the typed refusal of a completed table-identity operand.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if (in_array($node->name, ['table_ident', 'table_ident_nodb'], true)) {
            $name = $lowering->names->qualified($node);
        } elseif ($node->name === 'handler_stmt' && $lowering->form($node)->node(1)->name === 'ident') {
            $name = new \SqlSemantics\Statement\Identifier\QualifiedName($lowering->names->identifier($lowering->form($node)->node(1)));
        } else {
            return null;
        }
        $problem = (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->problem($name);

        return $problem === null ? null : new RejectedInput($problem, (new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? 0, in_array($lowering->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true));
    }

    /**
     * Reads table operands before lowering a completed program member.
     */
    public function within(Node $node, Lowering $lowering): ?RejectedInput
    {
        $pending = [$node];
        while ($pending !== []) {
            $part = array_pop($pending);
            $failure = $this->rejected($part, $lowering);
            if ($failure !== null) {
                return $failure;
            }
            foreach (array_reverse($part->children) as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return null;
    }

    /**
     * Legacy releases refuse table identifiers while reading their grammar operands.
     */
    public function completed(Node $node, Lowering $lowering): ?\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure
    {
        $failure = $this->rejected($node, $lowering);

        return $failure?->aborts === true ? new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($failure->problem, true) : null;
    }

    /**
     * Reads a modern CREATE TABLE target independently of its later definition operands.
     * No source tree is retained by the returned identifier.
     */
    public function created(Node $tree, \SqlSemantics\Contract\LanguageProfile $profile): ?\SqlSemantics\Statement\Identifier\QualifiedName
    {
        $node = $tree->find('create_table_stmt')[0] ?? null;
        if ($node === null || ($tree->tokens()[0] ?? null) !== ($node->tokens()[0] ?? null)) {
            return null;
        }
        $lowering = new Lowering(\SqlSemantics\Lowering\Productions::load(dirname(__DIR__, 3) . '/resources/productions/' . $profile->grammar->value . '.php'), new \SqlSemantics\Lowering\Leaves(), $profile);

        return $lowering->names->qualified($lowering->form($node)->node(4));
    }

    /**
     * Reads multiple-DELETE targets independently of rejected relation operands.
     * Only the outer command owns these names; stored bodies are not inspected.
     * A completed target list must precede the supplied refusal boundary.
     *
     * @return list<\SqlSemantics\Statement\Identifier\QualifiedName> The independently lowered target names
     */
    public function deleted(Node $tree, \SqlSemantics\Contract\LanguageProfile $profile, int $end = PHP_INT_MAX): array
    {
        $node = $tree->find('delete_stmt')[0] ?? $tree->find('delete')[0] ?? null;
        if ($node === null || ($tree->tokens()[0] ?? null) !== ($node->tokens()[0] ?? null)) {
            return [];
        }
        $lowering = new Lowering(\SqlSemantics\Lowering\Productions::load(dirname(__DIR__, 3) . '/resources/productions/' . $profile->grammar->value . '.php'), new \SqlSemantics\Lowering\Leaves(), $profile);
        $node = $node->find('single_multi')[0] ?? $node;
        foreach ($node->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['table_alias_ref_list', 'table_wild_list'], true)) {
                return ((new \SqlSemantics\Construction\SourceRanges())->span($child)[1] ?? PHP_INT_MAX) <= $end ? $lowering->dml->deleteTargets($child) : [];
            }
        }

        return [];
    }

}
