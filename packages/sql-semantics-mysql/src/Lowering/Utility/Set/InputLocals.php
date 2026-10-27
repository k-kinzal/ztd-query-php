<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Set;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Tracks names introduced by completed input reductions until their lexical block closes.
 *
 * This transient input state holds decoded names and offsets only. Conditions
 * and cursors do not introduce local variables; parameters outlive every body block.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class InputLocals
{
    /**
 * @var array<int, Name> Variable declarations at their input offsets
 */
    private array $names = [];

    /**
     * Observes completed declarations and block boundaries in reduction order.
     */
    public function observe(Node $node, Lowering $lowering): void
    {
        if ($node->name === 'sp_block_content') {
            $range = (new SourceRanges())->span($node);
            if ($range !== null) {
                $this->names = array_filter($this->names, static fn (int $offset): bool => $offset < $range[0] || $offset >= $range[1], ARRAY_FILTER_USE_KEY);
            }
            return;
        }
        if (!in_array($node->name, ['sp_decl_idents', 'sp_pdparam', 'sp_fdparam'], true)) {
            return;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node && $child->name === 'ident') {
                $range = (new SourceRanges())->span($child);
                if ($range !== null) {
                    $this->names[$range[0]] = $lowering->names->identifier($child);
                }
            }
        }
    }

    /**
     * Uses the ordinary semantic identity of program variables.
     */
    public function has(Name $name): bool
    {
        $weights = new GeneralWeights();
        $key = $weights->key($name->value);
        foreach ($this->names as $candidate) {
            if ($weights->key($candidate->value) === $key) {
                return true;
            }
        }

        return false;
    }
}
