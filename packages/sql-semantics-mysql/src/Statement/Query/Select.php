<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\SelectFacts;
use SqlSemantics\Platform\MySql\Rules\Query\SortScopes;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\LateOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Selection;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * One query block: SELECT with its modifiers, select list, FROM clause, filters, grouping, windows, ordering, limit, INTO and locking clauses.
 *
 * It is the query specification of every grammar generation. The ORDER BY,
 * LIMIT, INTO and locking clauses written after a single query block belong
 * to it, as the server attaches them; written after a set operation or a
 * parenthesized query they belong to a query expression or a query
 * statement instead. An ORDER BY or GROUP BY item that is an unsigned
 * integer is a select list position (MYSQL-ORDINAL-001) and must be given
 * as one. An INTO clause after the query clauses needs one of them, because
 * otherwise it is written as the INTO after the select list. A block of a
 * 5.6 subquery may write its ORDER BY and LIMIT after its locking clauses,
 * or a LIMIT after its own LIMIT that replaces it (LateOrdering).
 *
 * Rule: MYSQL-SELECT-001. The facts are derived by MYSQL-SELECT-FACTS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/5.7/en/select.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a selection
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a, b FROM t WHERE a > 1 GROUP BY a, b HAVING a > 2 ORDER BY a LIMIT 3');
 *     [count($query->statement->items), $query->statement->where !== null, $query->statement->having !== null, count($query->statement->orderBy)] // => [2, true, true, 1]
 * @example Depending on a table the context does not declare
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t');
 *     $query->field('a')->type instanceof \SqlSemantics\Statement\Type\Dependent // => true
 * @example Refusing a star after the first item
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')), new \SqlSemantics\Platform\MySql\Statement\Query\Star()]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Select implements Statement, Query, Selection
{
    use Snapshot;

    /**
     * @var list<SelectOption> The modifiers in written order
     */
    public readonly array $options;

    /**
     * @var non-empty-list<SelectExpression|Star|TableWildcard> The select list in written order
     */
    public readonly array $items;

    /**
     * @var list<WindowDefinition> The named windows in written order
     */
    public readonly array $windows;

    /**
     * @var list<OrderItem> The ORDER BY items in written order
     */
    public readonly array $orderBy;

    /**
     * @var list<LockingClause> The locking clauses in written order
     */
    public readonly array $locking;

    /**
     * @param list<SelectOption> $options The modifiers in written order
     * @param list<Node> $items The select list of expressions and qualified stars; at least one item, an unqualified star only first
     * @param Relation|null $from The FROM clause
     * @param Scalar|null $where The row predicate
     * @param Grouping|null $groupBy The GROUP BY clause
     * @param Scalar|null $having The group predicate
     * @param list<WindowDefinition> $windows The named windows
     * @param Scalar|null $qualify The window predicate of QUALIFY
     * @param list<OrderItem> $orderBy The ORDER BY items
     * @param Limit|null $limit The LIMIT clause
     * @param ProcedureAnalyse|null $procedure The PROCEDURE ANALYSE clause of the 5.x grammars
     * @param list<LockingClause> $locking The locking clauses
     * @param IntoDestination|null $into The INTO destination
     * @param IntoPosition|null $intoPosition Where INTO is written; given exactly when there is a destination
     * @param LateOrdering|null $late The ORDER BY and LIMIT a 5.6 subquery writes after the locking clauses or the LIMIT of the block
     */
    public function __construct(
        array $options,
        array $items,
        public readonly ?Relation $from = null,
        public readonly ?Scalar $where = null,
        public readonly ?Grouping $groupBy = null,
        public readonly ?Scalar $having = null,
        array $windows = [],
        public readonly ?Scalar $qualify = null,
        array $orderBy = [],
        public readonly ?Limit $limit = null,
        public readonly ?ProcedureAnalyse $procedure = null,
        array $locking = [],
        public readonly ?IntoDestination $into = null,
        public readonly ?IntoPosition $intoPosition = null,
        public readonly ?LateOrdering $late = null,
    ) {
        $this->options = Check::listOf($options, SelectOption::class, 'The modifiers of a selection are select options.');
        $list = [];
        foreach (Check::listOf($items, Node::class, 'A selection projects at least one item.', 1) as $position => $item) {
            Check::input($item instanceof SelectExpression || $item instanceof TableWildcard || ($item instanceof Star && $position === 0), 'A select list item is an expression or a qualified star, and an unqualified star comes first.');
            $list[] = $item;
        }
        $this->items = $list;
        $this->windows = Check::listOf($windows, WindowDefinition::class, 'The WINDOW clause holds window definitions.');
        $this->orderBy = Check::listOf($orderBy, OrderItem::class, 'ORDER BY holds ordering items.');
        $this->locking = Check::listOf($locking, LockingClause::class, 'The locking clauses of a selection are locking clauses.');
        (new SortScopes())->check([...$this->orderBy, ...($groupBy === null ? [] : $groupBy->items)]);
        Check::input(($into === null) === ($intoPosition === null), 'An INTO destination is written at exactly one position.');
        Check::input($intoPosition !== IntoPosition::AfterQuery || $this->clauses(), 'An INTO after the query clauses follows at least one of them.');
        Check::input($intoPosition !== IntoPosition::AfterLocking || $this->locking !== [], 'An INTO after the locking clauses follows at least one of them.');
        Check::input($late === null || $late->orderBy === [] || ($this->orderBy === [] && $limit === null), 'An ORDER BY written after a block that orders or limits its rows orders the rows of the block.');
        Check::input($late === null || $this->locking !== [] || $limit !== null, 'An ORDER BY or LIMIT of the block is written in place unless locking clauses or a LIMIT of the block precede it.');
        Check::input($late === null || ($into === null && $procedure === null), 'A block with a late ordering has no INTO or PROCEDURE ANALYSE.');
    }

    /**
     * Tells whether a clause after the select list and before INTO is written.
     */
    public function clauses(): bool
    {
        return $this->from !== null || $this->where !== null || $this->groupBy !== null || $this->having !== null || $this->windows !== []
            || $this->qualify !== null || $this->orderBy !== [] || $this->limit !== null || $this->procedure !== null;
    }

    /**
     * Tells whether a clause written after the selection proper is present: ORDER BY, LIMIT, PROCEDURE, a trailing INTO or a locking clause.
     */
    public function trailed(): bool
    {
        return $this->orderBy !== [] || $this->limit !== null || $this->procedure !== null || $this->locking !== [] || $this->late !== null
            || ($this->into !== null && $this->intoPosition !== IntoPosition::AfterItems);
    }

    /**
     * Answers the input relation.
     */
    public function input(): ?Relation
    {
        return $this->from;
    }

    /**
     * Derives the selection as a statement root and records its rows as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        Check::input($this->late === null, 'An ORDER BY or LIMIT after the locking clauses or the LIMIT of a block is written in a subquery only.');
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Derives the input, every clause and the output fields.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        Check::input($this->late === null || $derivation->context->profile->grammar === GrammarRelease::MySql5651, 'An ORDER BY or LIMIT after the locking clauses or the LIMIT of a block needs MySQL 5.6.');

        return (new SelectFacts())->derive($this, $derivation, $outer);
    }

    /**
     * Writes the clauses in grammar order.
     */
    public function render(Output $out): void
    {
        $out->keyword('SELECT');
        foreach ($this->options as $option) {
            $out->keyword($option->value);
        }
        $out->list($this->items);
        $this->renderInto($out, IntoPosition::AfterItems);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        $out->node($this->groupBy);
        if ($this->having !== null) {
            $out->keyword('HAVING')->node($this->having);
        }
        if ($this->windows !== []) {
            $out->keyword('WINDOW')->list($this->windows);
        }
        if ($this->qualify !== null) {
            $out->keyword('QUALIFY')->node($this->qualify);
        }
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit)->node($this->procedure);
        $this->renderInto($out, IntoPosition::AfterQuery);
        foreach ($this->locking as $clause) {
            $out->node($clause);
        }
        $this->renderInto($out, IntoPosition::AfterLocking);
        $out->node($this->late);
    }

    /**
     * Writes the INTO clause when it is written at the given position.
     */
    public function renderInto(Output $out, IntoPosition $position): void
    {
        if ($this->intoPosition === $position) {
            $out->keyword('INTO')->node($this->into);
        }
    }
}
