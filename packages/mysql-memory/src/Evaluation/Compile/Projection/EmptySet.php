<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Projection;

use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Session\Diagnostics;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Statement\Query;

/**
 * Determines whether an empty bounded set still exposes its buffered fields.
 *
 * MySQL 9.1 invalidates a nonnullable UNION output when at least one operand is a tableless
 * block prepared to return a row directly. HAVING, local row bounds, table references and
 * conditions not resolved to true keep that operand on the buffered path. This distinction
 * is observable even when LIMIT 0 prevents every operand from executing (verified through SQL).
 *
 * @visibility MySqlMemory\Evaluation
 */
final class EmptySet
{
    /**
     * @param Compiler $compiler The statement compiler
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Tells whether null-safe comparison can inspect an empty set's stored fields.
     */
    public function readable(SetOperation $query, QueryPlan $plan): bool
    {
        if ($this->compiler->settings->release() !== GrammarRelease::MySql910 || !$plan->root instanceof Limit || $query->operator !== SetOperator::Union) {
            return true;
        }
        $nonnull = array_filter($plan->domains, static fn ($domain): bool => !$domain->nullable);

        return $nonnull === [] || !$this->direct($query);
    }

    /**
     * Finds an operand prepared without a table, group filter or local row bound.
     */
    public function direct(Query $query): bool
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            if ($query instanceof QueryExpression && ($query->limit !== null || $query->with !== null)) {
                return false;
            }
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if ($query instanceof SetOperation) {
            return $query->operator === SetOperator::Union && (($query->left instanceof Query && $this->direct($query->left)) || $this->direct($query->right));
        }
        if (!$query instanceof Select || ($query->from !== null && !$query->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual) || $query->having !== null || $query->limit !== null || $query->groupBy !== null || $query->qualify !== null) {
            return false;
        }

        return $query->where === null || $this->selected($query);
    }

    /**
     * Proves a pure preparation-time condition true without publishing its diagnostics.
     */
    public function selected(Select $query): bool
    {
        $where = $query->where;
        if ($where === null || $this->compiler->constancy($where) !== Constancy::Resolved) {
            return false;
        }
        $context = $this->compiler->connection->context;
        $frame = new Frame(new Context($context->modes, new Diagnostics(), clone $context->variables, $context->started));
        try {
            $condition = $this->compiler->compile($where, new Scope());

            return Convert::toBool($condition->evaluate($frame), $condition->domain(), $frame->context) === true;
        } catch (\MySqlMemory\Error\SqlError) {
            return false;
        }
    }
}
