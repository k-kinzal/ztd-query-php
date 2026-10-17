<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Projection;

use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\BufferedRead;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Subquery\CachedProjection;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as ResolvedDomain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Type\Known;

/**
 * Determines whether an empty bounded set still exposes its buffered fields.
 *
 * MySQL 9.1 invalidates a nonnullable UNION output when at least one operand is a tableless
 * block prepared to return a row directly. HAVING, local row bounds, table references and
 * conditions not resolved to true keep that operand on the buffered path. This distinction
 * is observable even when LIMIT 0 prevents every operand from executing (verified through SQL).
 * MySQL 5.6 retains the initial field state determined by the first UNION operand,
 * independently of later operands and rows discarded by an exhausted OFFSET.
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
     * Builds the fields inspected after an empty set result. MySQL 5.6 uses
     * the first operand's initial NULL markers even after sorting or discarding rows.
     * A typed nullable expression starts at its zero value; an untyped NULL remains NULL.
     * Verified against recorded native empty-UNION result comparisons.
     *
     * @return list<Evaluable>
     */
    public function projection(SetOperation $query, QueryPlan $plan): array
    {
        if ($this->compiler->settings->release() !== GrammarRelease::MySql5651) {
            return array_map(static fn (Domain $domain, int $position): Evaluable => new BufferedRead($domain, $position), $plan->domains, array_keys($plan->domains));
        }
        $first = $query;
        while ($first instanceof SetOperation || $first instanceof LeadingUnion || $first instanceof ParenthesizedQuery || $first instanceof QueryExpression) {
            $first = match (true) {
                $first instanceof SetOperation, $first instanceof LeadingUnion => $first->left,
                $first instanceof ParenthesizedQuery => $first->query,
                default => $first->body,
            };
        }
        $slots = $this->compiler->facts->query($first)->shape->slots;
        $result = [];
        foreach ($plan->domains as $position => $domain) {
            $type = $slots[$position]->type;
            $nullable = $type instanceof Known && $type->descriptor instanceof ResolvedDomain ? $type->descriptor->kind === Kind::Null : null;
            $date = $type instanceof Known && $type->descriptor instanceof ResolvedDomain && $type->descriptor->kind === Kind::Date;
            $value = new BufferedRead($domain, $position, $date ? null : $nullable, true);
            $result[] = $date ? new CachedProjection($value, true, true) : $value;
        }

        return $result;
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
