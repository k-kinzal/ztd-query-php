<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Statement\Query;

/**
 * The clauses written after a query during lowering: ORDER BY, LIMIT, PROCEDURE ANALYSE, a trailing INTO and the locking clauses.
 *
 * A lowering-time value: it collects the clauses until the query they
 * belong to is known (MYSQL-SELECT-001) and is never part of a model.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class Trailer
{
    /**
     * @param list<OrderItem> $orderBy The ORDER BY items
     * @param Limit|null $limit The LIMIT clause
     * @param ProcedureAnalyse|null $procedure The PROCEDURE ANALYSE clause
     * @param list<LockingClause> $locking The locking clauses
     * @param IntoDestination|null $into The trailing INTO destination
     * @param IntoPosition|null $intoPosition Where the trailing INTO is written
     */
    public function __construct(
        public readonly array $orderBy = [],
        public readonly ?Limit $limit = null,
        public readonly ?ProcedureAnalyse $procedure = null,
        public readonly array $locking = [],
        public readonly ?IntoDestination $into = null,
        public readonly ?IntoPosition $intoPosition = null,
    ) {
    }

    /**
     * Tells whether no clause is written.
     */
    public function empty(): bool
    {
        return $this->orderBy === [] && $this->limit === null && $this->procedure === null && $this->locking === [] && $this->into === null;
    }

    /**
     * Combines these clauses with the ones written after them around the same query.
     *
     * Locking clauses accumulate in written order. A clause the server
     * accepts only once is refused.
     *
     * @throws AnalysisException When ORDER BY, INTO or PROCEDURE ANALYSE is written twice, which no grammar derives and the server rejects while parsing (ER_SYNTAX_ERROR)
     * @throws ImplementationGap When LIMIT is written twice, where the 5.6 server lets the later one replace the earlier
     */
    public function then(self $later): self
    {
        if ($this->empty()) {
            return $later;
        }
        if ($later->empty()) {
            return $this;
        }
        if ($this->limit !== null && $later->limit !== null) {
            throw ImplementationGap::rule('a query block with two LIMIT clauses, where the later replaces the earlier');
        }
        if (($this->orderBy !== [] && $later->orderBy !== []) || ($this->into !== null && $later->into !== null) || ($this->procedure !== null && $later->procedure !== null)) {
            throw new AnalysisException('Incorrect usage of ORDER BY, INTO or PROCEDURE: the clause is written twice for one query.');
        }

        return new self([...$this->orderBy, ...$later->orderBy], $this->limit ?? $later->limit, $this->procedure ?? $later->procedure, [...$this->locking, ...$later->locking], $this->into ?? $later->into, $this->intoPosition ?? $later->intoPosition);
    }

    /**
     * Wraps a query that is not a single query block in the clauses: ordering and limit first, then INTO and locking.
     *
     * @throws AnalysisException When PROCEDURE ANALYSE follows a query that is not a single query block (ER_WRONG_USAGE of the 5.6 `procedure_analyse_clause` action and of 5.7 `PT_procedure_analyse::contextualize`: PROCEDURE ANALYSE belongs to the outermost first query block)
     */
    public function wrap(Query $query): Query
    {
        if ($this->procedure !== null) {
            throw new AnalysisException('Incorrect usage of PROCEDURE and UNION: PROCEDURE ANALYSE follows a single query block only.');
        }
        if ($this->orderBy !== [] || $this->limit !== null) {
            $query = new QueryExpression(null, $query, $this->orderBy, $this->limit);
        }
        if ($this->locking !== [] || $this->into !== null) {
            $query = new QueryStatement($query, $this->locking, $this->into, $this->intoPosition);
        }

        return $query;
    }
}
