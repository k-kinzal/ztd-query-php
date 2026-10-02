<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Selection;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A selection: projected items over an optional input, filtered by an optional predicate.
 *
 * Rule: PG-SELECT-001 (slice — the query family completes or replaces this
 * with the full model of PostgreSQL's `SelectStmt`). The input relation is
 * derived in the environment of the enclosing query. Items and predicate see
 * the input occurrence and, beyond it, the enclosing query. The select list
 * may be empty. Source: https://www.postgresql.org/docs/17/sql-select.html. Status: Specified.
 *
 * @visibility public
 * @example Reading the parts of a selection
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT a, 1 FROM t WHERE a > 1');
 *     [count($query->statement->targets), $query->statement->where !== null, $query->toString()] // => [2, true, 'SELECT a, 1 FROM t WHERE a > 1']
 */
final class Select implements Statement, Query, Selection
{
    use Snapshot;

    /**
     * @var list<Target> The select-list items in output order
     */
    public readonly array $targets;

    /**
     * @param list<Target> $targets The select-list items in output order
     * @param TableInput|null $from The input relation
     * @param Scalar|null $where The row predicate
     */
    public function __construct(array $targets, public readonly ?TableInput $from = null, public readonly ?Scalar $where = null)
    {
        $this->targets = Check::listOf($targets, Target::class, 'A select list holds select-list items.');
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
     * Derives the input, the predicate and the output fields.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        $relations = [];
        if ($this->from !== null) {
            $fact = $derivation->relation($this->from, $outer);
            $relations[] = new VisibleRelation($this->from, $fact->shape, $this->from->alias, $this->from->name());
        }
        $environment = new Environment($derivation->context, $outer, $relations);
        if ($this->where !== null) {
            $derivation->scalar($this->where, $environment);
        }
        $fields = [];
        foreach ($this->targets as $target) {
            array_push($fields, ...$target->project($derivation, $environment, count($fields)));
        }

        return new QueryFact($fields, $derivation->context->columnNames);
    }

    /**
     * Writes the clauses in grammar order.
     */
    public function render(Output $out): void
    {
        $out->keyword('SELECT')->list($this->targets);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
