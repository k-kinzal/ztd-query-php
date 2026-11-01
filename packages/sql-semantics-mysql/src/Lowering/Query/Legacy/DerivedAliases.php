<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Lowering\Lowering;

/**
 * Checks aliases only once a legacy FROM list distinguishes relations from query parentheses.
 * An inner unaliased SELECT may still receive its alias outside another pair of parentheses.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DerivedAliases
{
    /**
     * Retains the original boundary of a missing alias without publishing an invalid relation.
     * Invalid table operands are left to the ordinary name check and its ordering rules.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if ($node->name !== 'join_table_list') {
            return null;
        }
        if ((new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->within($node, $lowering) !== null) {
            return null;
        }
        try {
            (new JoinRule($lowering))->list($node);
        } catch (RejectedInput $failure) {
            return $failure;
        } catch (AnalysisException) {
            // Malformed nested joins retain their ordinary syntax refusal during full lowering.
        }

        return null;
    }
}
