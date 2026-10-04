<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\SelectFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Positions;
use SqlSemantics\Platform\PostgreSql\Rules\Query\TargetNames;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\DistinctClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSet;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Selection;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A selection: the SELECT form of PostgreSQL's `SelectStmt` without set operation.
 *
 * Rule: PG-SELECT-001. Holds, in grammar order, DISTINCT [ON], the select
 * list, INTO, FROM (one item, or a comma list of several), WHERE, GROUP BY
 * with its quantifier, HAVING, WINDOW, and the ORDER BY, LIMIT and locking
 * clauses written directly after it. The facts follow PG-SELECT-SCOPE-001.
 * An integer constant in GROUP BY must be given as an output position.
 * DISTINCT requires a select list.
 * Source: https://www.postgresql.org/docs/17/sql-select.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a selection
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT DISTINCT a, 1 FROM t WHERE a > 1 GROUP BY a HAVING a > 2 ORDER BY a LIMIT 3');
 *     [count($query->statement->targets), $query->statement->distinct !== null, count($query->statement->groupBy), count($query->statement->options->order), $query->toString()] // => [2, true, 1, 1, 'SELECT DISTINCT a, 1 FROM t WHERE a > 1 GROUP BY a HAVING a > 2 ORDER BY a LIMIT 3']
 * @example Refusing DISTINCT without a select list
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([], distinct: new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\DistinctClause()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Select implements Statement, Query, Selection, OutputNaming
{
    use Snapshot;

    /**
     * @var list<Target> The select-list items in output order
     */
    public readonly array $targets;

    /**
     * @var list<Scalar|GroupingSet> The GROUP BY items in written order
     */
    public readonly array $groupBy;

    /**
     * @var list<WindowDefinition> The named windows in written order
     */
    public readonly array $windows;

    /**
     * @param list<Target> $targets The select-list items in output order
     * @param Relation|null $from The FROM item, or the list of FROM items
     * @param Scalar|null $where The row predicate
     * @param list<Scalar|GroupingSet> $groupBy The GROUP BY items
     * @param Scalar|null $having The group predicate
     * @param list<WindowDefinition> $windows The named windows
     * @param DistinctClause|null $distinct The DISTINCT clause
     * @param IntoClause|null $into The table SELECT INTO creates
     * @param SetQuantifier|null $groupQuantifier The ALL or DISTINCT written after GROUP BY
     * @param SelectOptions|null $options The ORDER BY, LIMIT and locking clauses written after the selection
     */
    public function __construct(
        array $targets,
        public readonly ?Relation $from = null,
        public readonly ?Scalar $where = null,
        array $groupBy = [],
        public readonly ?Scalar $having = null,
        array $windows = [],
        public readonly ?DistinctClause $distinct = null,
        public readonly ?IntoClause $into = null,
        public readonly ?SetQuantifier $groupQuantifier = null,
        public readonly ?SelectOptions $options = null,
    ) {
        $this->targets = Check::listOf($targets, Target::class, 'A select list holds select-list items.');
        $this->groupBy = (new ClosedList())->of($groupBy, [Scalar::class, GroupingSet::class], 'A GROUP BY item is an expression or a grouping set.');
        foreach ($this->groupBy as $item) {
            Check::input($item instanceof GroupingSet || (new Positions())->value($item) === null, 'An integer constant in GROUP BY is an output position.');
        }
        $this->windows = Check::listOf($windows, WindowDefinition::class, 'The WINDOW clause holds window definitions.');
        Check::input($distinct === null || $this->targets !== [], 'DISTINCT requires a select list.');
        Check::input($groupQuantifier === null || $this->groupBy !== [], 'A GROUP BY quantifier is written with GROUP BY items.');
        foreach ($distinct->on ?? [] as $expression) {
            Check::input((new Positions())->value($expression) === null, 'An integer constant in DISTINCT ON is an output position.');
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
     * Answers the name of the first output column, when the select list fixes it.
     */
    public function outputName(): ?Name
    {
        return (new TargetNames())->first($this->targets);
    }

    /**
     * Derives the selection as a statement root.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new QueryRoots())->derive($this, $derivation);
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
        $out->keyword('SELECT')->node($this->distinct)->list($this->targets)->node($this->into);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->groupBy !== []) {
            $out->keyword('GROUP', 'BY');
            if ($this->groupQuantifier !== null) {
                $out->keyword($this->groupQuantifier->value);
            }
            $out->list($this->groupBy);
        }
        if ($this->having !== null) {
            $out->keyword('HAVING')->node($this->having);
        }
        if ($this->windows !== []) {
            $out->keyword('WINDOW')->list($this->windows);
        }
        $out->node($this->options);
    }
}
