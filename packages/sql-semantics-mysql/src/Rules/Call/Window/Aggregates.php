<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call\Window;

use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind;

/**
 * Identifies aggregate modifiers with no ordinary window invocation domain.
 *
 * MIN and MAX accept DISTINCT as an inert modifier. SUM, AVG and COUNT do
 * not accept it in a window. GROUP_CONCAT has no window invocation domain.
 * Verified through black-box observations on MySQL 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Aggregates
{
    /**
     * Answers a fixed invocation condition independently of arguments or names.
     */
    public function limitation(AggregateFunction|DeferredAggregateKind $function, bool $distinct): ?WindowingLimit
    {
        return match (true) {
            $function === DeferredAggregateKind::GroupConcat => WindowingLimit::GroupConcat,
            $distinct && in_array($function, [AggregateFunction::Sum, AggregateFunction::Average, AggregateFunction::Count], true) => WindowingLimit::DistinctAggregate,
            default => null,
        };
    }
}
