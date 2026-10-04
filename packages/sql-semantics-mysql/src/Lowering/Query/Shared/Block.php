<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\LateOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * The parts of one query block during lowering, before the clauses written after it are known.
 *
 * A lowering-time value: the trailing clauses of the block are collected in
 * a trailer until the block is finished into a selection. An INTO written
 * after the clauses of a block that writes none of them is the INTO after
 * the select list, which is how the parser reads its text. The ORDER BY
 * and LIMIT a 5.6 subquery writes after a block are added by `finish()`.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class Block
{
    /**
     * @param list<SelectOption> $options The modifiers
     * @param list<SelectExpression|Star|TableWildcard> $items The select list
     * @param IntoDestination|null $into The INTO destination after the select list
     * @param Relation|null $from The FROM clause
     * @param Scalar|null $where The row predicate
     * @param Grouping|null $groupBy The GROUP BY clause
     * @param Scalar|null $having The group predicate
     * @param list<WindowDefinition> $windows The named windows
     * @param Scalar|null $qualify The QUALIFY predicate
     * @param Trailer $trailer The clauses written after the block
     */
    public function __construct(
        public readonly array $options,
        public readonly array $items,
        public readonly ?IntoDestination $into = null,
        public readonly ?Relation $from = null,
        public readonly ?Scalar $where = null,
        public readonly ?Grouping $groupBy = null,
        public readonly ?Scalar $having = null,
        public readonly array $windows = [],
        public readonly ?Scalar $qualify = null,
        public readonly Trailer $trailer = new Trailer(),
    ) {
    }

    /**
     * Answers the block with further clauses written after it.
     *
     * @throws AnalysisException When a clause is written twice
     */
    public function then(Trailer $later): self
    {
        return new self($this->options, $this->items, $this->into, $this->from, $this->where, $this->groupBy, $this->having, $this->windows, $this->qualify, $this->trailer->then($later));
    }

    /**
     * Finishes the block with the ORDER BY and LIMIT a 5.6 subquery writes after it (`opt_union_order_or_limit`).
     *
     * An ORDER BY after a block that already orders or limits its rows
     * orders the rows of the block in a query level of its own (the 5.6
     * `order_clause` action adds a fake query block). Otherwise the clauses
     * are the block's own: written in place when nothing of the block
     * stands between, and kept as a late ordering after the block's locking
     * clauses or after its own LIMIT, which a later LIMIT replaces.
     *
     * @throws AnalysisException When INTO is written twice (see `select()`)
     */
    public function finish(Trailer $later): Query
    {
        $trailer = $this->trailer;
        if ($later->empty()) {
            return $this->select();
        }
        if ($later->orderBy !== [] && ($trailer->orderBy !== [] || $trailer->limit !== null)) {
            return $later->wrap($this->select());
        }
        $plain = $later->procedure === null && $later->locking === [] && $later->into === null && $trailer->into === null && $trailer->procedure === null;
        if ($plain && ($trailer->locking !== [] || ($trailer->limit !== null && $later->limit !== null))) {
            return $this->select(new LateOrdering($later->orderBy, $later->limit));
        }

        return $this->then($later)->select();
    }

    /**
     * Answers the block without the clauses written after it.
     */
    public function bare(): self
    {
        return new self($this->options, $this->items, $this->into, $this->from, $this->where, $this->groupBy, $this->having, $this->windows, $this->qualify);
    }

    /**
     * Finishes the block into a selection.
     *
     * @param LateOrdering|null $late The ORDER BY and LIMIT a 5.6 subquery writes after the locking clauses or the LIMIT of the block
     * @throws AnalysisException When INTO is written twice, which the server rejects while parsing (ER_SYNTAX_ERROR of the 5.7 `select_part2` action, ER_MULTIPLE_INTO_CLAUSES of 8.0 `PT_select_stmt::make_cmd`, right after contextualizing; the 5.6 grammar has no such form)
     */
    public function select(?LateOrdering $late = null): Select
    {
        $trailer = $this->trailer;
        if ($this->into !== null && $trailer->into !== null) {
            throw new AnalysisException('Misplaced INTO clause: a query block has one INTO clause.');
        }
        $into = $this->into ?? $trailer->into;
        $position = $this->into !== null ? IntoPosition::AfterItems : $trailer->intoPosition;
        $plain = $this->from === null && $this->where === null && $this->groupBy === null && $this->having === null && $this->windows === [] && $this->qualify === null
            && $trailer->orderBy === [] && $trailer->limit === null && $trailer->procedure === null;
        if ($position === IntoPosition::AfterQuery && $plain) {
            $position = IntoPosition::AfterItems;
        }

        return new Select($this->options, $this->items, $this->from, $this->where, $this->groupBy, $this->having, $this->windows, $this->qualify, $trailer->orderBy, $trailer->limit, $trailer->procedure, $trailer->locking, $into, $position, $late);
    }
}
