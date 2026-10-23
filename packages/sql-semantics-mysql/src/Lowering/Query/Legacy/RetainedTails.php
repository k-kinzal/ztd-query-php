<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;

/**
 * Assigns legacy SELECT tails without discarding retained projection or filter operands.
 *
 * The final unparenthesized block gives its ordering and limit to the complete
 * UNION. Parenthesized operands keep their own clauses. Earlier blocks retain
 * the MySQL 5.6 LIMIT role and exclude that role on later releases.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class RetainedTails
{
    /**
     * Detaches the final block's ordinary ordering and limit for the complete UNION.
     *
     * @return array{\SqlSemantics\Statement\Query, Trailer} The complete bare operand and its original tail
     * @throws AnalysisException When PROCEDURE ANALYSE appears in a UNION
     */
    public function detach(DeferredBody $body): array
    {
        $selection = $body->selection;
        if ($selection->procedure !== null) {
            throw new AnalysisException('Incorrect usage of PROCEDURE and subquery: PROCEDURE ANALYSE follows a single query block only.');
        }
        $bare = new DeferredBody(new DeferredSelection($selection->items, $selection->from, $selection->where, $selection->options, $selection->groupBy, $selection->having, windows: $selection->windows));
        return [$bare, new Trailer($selection->orderBy, $selection->limit)];
    }

    /**
     * Combines a retained final operand with its independently owned result tail.
     */
    public function union(\SqlSemantics\Statement\Query|\SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion $left, ?\SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier $quantifier, DeferredBody $right, Trailer $after): \SqlSemantics\Statement\Query
    {
        if (!$after->empty() && ($right->selection->orderBy !== [] || $right->selection->limit !== null)) {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation($left, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, $quantifier, $right, $after->orderBy, $after->limit);
        }
        [$bare, $tail] = $this->detach($right);
        return $tail->then($after)->wrap(new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation($left, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, $quantifier, $bare));
    }

    /**
     * Rejects result analysis before a retained block receives its subquery role.
     *
     * @throws AnalysisException When PROCEDURE ANALYSE occurs in a subquery
     */
    public function subquery(\SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block|DeferredSelection $block): void
    {
        $procedure = $block instanceof DeferredSelection ? $block->procedure : $block->trailer->procedure;
        if ($procedure !== null) {
            throw new AnalysisException('Incorrect usage of PROCEDURE and subquery: PROCEDURE ANALYSE belongs to the outermost query block.');
        }
    }

    /**
     * Checks the written clauses of an earlier unparenthesized retained block.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When the operand owns a clause forbidden before UNION
     */
    public function earlier(DeferredBody $body, GrammarRelease $release): void
    {
        $selection = $body->selection;
        if ($selection->orderBy !== [] || ($selection->limit !== null && $release !== GrammarRelease::MySql5651)) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSetClause($body), 0, false);
        }
        if ($selection->procedure !== null) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSetClause($body), 0, false);
        }
    }
}
