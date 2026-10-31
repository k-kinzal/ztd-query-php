<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Statement\Query;

/**
 * Checks the grammar position of a query submitted as a statement.
 *
 * Legacy subqueries and INSERT sources admit no-FROM filters that standalone
 * SELECT does not. Their bodies remain valid query objects. Observed on an
 * isolated MySQL 5.7.44 server and checked against the shipped parser profiles.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StatementQuery
{
    /**
     * Rejects forms whose syntax exists only in a query operand position.
     */
    public function check(Query $query): void
    {
        $refusal = $this->refusal($query);
        Check::input($refusal === null, $refusal ?? '');
    }

    /**
     * Describes the first form of the query that exists in a query operand position only, or null when the query stands alone.
     */
    public function refusal(Query $query): ?string
    {
        if ($this->problem($query) !== null) {
            return 'A statement query cannot combine ALL and DISTINCT in one query block.';
        }
        foreach ($this->blocks($query) as $block) {
            if ($block instanceof OrderedSetOperation) {
                return 'An ordered legacy set operation belongs to a subquery only.';
            }
            if ($block instanceof Select && $block->late !== null) {
                return 'An ORDER BY or LIMIT after the locking clauses or the LIMIT of a block is written in a subquery only.';
            }
            if (($block instanceof Select || $block instanceof DeferredSelection) && $block->procedure !== null && $block->from === null) {
                return 'A standalone PROCEDURE ANALYSE query requires a FROM clause.';
            }
        }

        return null;
    }

    /**
     * Finds contradictory quantifiers on the statement query's own block spine.
     * Expression subqueries and unreferenced definitions have separate roles.
     */
    public function problem(Query $query): ?\SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict
    {
        foreach ($this->blocks($query) as $block) {
            if (($block instanceof Select || $block instanceof DeferredSelection) && in_array(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, $block->options, true) && in_array(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct, $block->options, true)) {
                return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct);
            }
        }
        return null;
    }

    /**
     * Answers whether the statement's query blocks need modern no-FROM syntax.
     */
    public function requiresModern(Query $query): bool
    {
        foreach ($this->blocks($query) as $block) {
            if (($block instanceof Select || $block instanceof DeferredSelection) && $block->from === null && ($block->where !== null || $block->groupBy !== null || $block->having !== null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collects statement-level bodies without entering scalar subqueries or CTE definitions.
     *
     * @return non-empty-list<Query|DeferredSelection>
     */
    public function blocks(Query|LeadingUnion $query): array
    {
        return match (true) {
            $query instanceof DeferredBody => [$query->selection],
            $query instanceof QueryExpression => $this->blocks($query->body),
            $query instanceof ParenthesizedQuery, $query instanceof QueryStatement => $this->blocks($query->query),
            $query instanceof SetOperation, $query instanceof LeadingUnion => [...$this->blocks($query->left), ...$this->blocks($query->right)],
            default => [$query],
        };
    }
}
