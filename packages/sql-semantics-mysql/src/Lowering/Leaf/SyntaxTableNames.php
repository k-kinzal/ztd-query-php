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

}
