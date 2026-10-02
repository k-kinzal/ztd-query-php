<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Rules\Query\Ordinals;
use SqlSemantics\Platform\Sqlite\Rules\Query\SelectFacts;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Selection;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A selection: result columns over an optional input, with its filter, grouping, windows, ordering and limit.
 *
 * Rule: SQLITE-SELECT-001. The facts are derived by SQLITE-SELECT-SCOPE-001.
 * An ORDER BY or GROUP BY term that is an integer constant must be given as
 * a result column position, because that is how SQLite reads it.
 * Source: https://sqlite.org/lang_select.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a selection
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t WHERE a > 1 GROUP BY a HAVING count(*) > 1 ORDER BY a LIMIT 3');
 *     [count($query->statement->columns), $query->statement->where !== null, count($query->statement->groupBy), $query->statement->having !== null, count($query->statement->orderBy), $query->statement->limit !== null] // => [1, true, 1, true, 1, true]
 * @example Refusing an integer constant as an ordinary ordering expression
 *     new \SqlSemantics\Platform\Sqlite\Statement\Query\Select([new \SqlSemantics\Platform\Sqlite\Statement\Query\Star()], null, null, [], null, [], [new \SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Select implements Statement, Query, Selection
{
    use Snapshot;

    /**
     * @var non-empty-list<ResultColumn|Star|TableStar> The result columns in written order
     */
    public readonly array $columns;

    /**
     * @var list<Scalar> The GROUP BY terms in written order
     */
    public readonly array $groupBy;

    /**
     * @var list<WindowDefinition> The named windows in written order
     */
    public readonly array $windows;

    /**
     * @var list<SortTerm> The ORDER BY terms in written order
     */
    public readonly array $orderBy;

    /**
     * @param list<ResultColumn|Star|TableStar> $columns The result columns in written order; at least one
     * @param Relation|null $from The input relation
     * @param Scalar|null $where The row predicate
     * @param list<Scalar> $groupBy The GROUP BY terms
     * @param Scalar|null $having The group predicate
     * @param list<WindowDefinition> $windows The named windows
     * @param list<SortTerm> $orderBy The ORDER BY terms
     * @param Limit|null $limit The LIMIT clause
     * @param SetQuantifier|null $quantifier The written DISTINCT or ALL
     * @throws InvalidConstruction When a part is not what the clause admits
     */
    public function __construct(
        array $columns,
        public readonly ?Relation $from = null,
        public readonly ?Scalar $where = null,
        array $groupBy = [],
        public readonly ?Scalar $having = null,
        array $windows = [],
        array $orderBy = [],
        public readonly ?Limit $limit = null,
        public readonly ?SetQuantifier $quantifier = null,
    ) {
        $list = [];
        foreach ($columns as $column) {
            if (!$column instanceof ResultColumn && !$column instanceof Star && !$column instanceof TableStar) {
                throw new InvalidConstruction('A result column is an expression, a star or a qualified star.');
            }
            $list[] = $column;
        }
        Check::input($list !== [] && array_is_list($columns), 'A selection projects at least one result column.');
        $this->columns = $list;
        $this->groupBy = Check::listOf($groupBy, Scalar::class, 'GROUP BY terms are expressions.');
        $this->windows = Check::listOf($windows, WindowDefinition::class, 'The WINDOW clause holds window definitions.');
        $this->orderBy = Check::listOf($orderBy, SortTerm::class, 'ORDER BY terms are ordering terms.');
        $ordinals = new Ordinals();
        foreach ([...$this->groupBy, ...array_map(static fn (SortTerm $term): Scalar => $term->expression, $this->orderBy)] as $term) {
            Check::input($term instanceof OutputOrdinal || $ordinals->value($term) === null, 'An integer constant in ORDER BY or GROUP BY is a result column position.');
        }
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
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Derives the input, every clause and the output fields.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new SelectFacts())->derive($this, $derivation, $outer);
    }

    /**
     * Writes the clauses in grammar order.
     */
    public function render(Output $out): void
    {
        $out->keyword('SELECT');
        if ($this->quantifier !== null) {
            $out->keyword($this->quantifier->value);
        }
        $out->list($this->columns);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->groupBy !== []) {
            $out->keyword('GROUP', 'BY')->list($this->groupBy);
        }
        if ($this->having !== null) {
            $out->keyword('HAVING')->node($this->having);
        }
        if ($this->windows !== []) {
            $out->keyword('WINDOW')->list($this->windows);
        }
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit);
    }
}
