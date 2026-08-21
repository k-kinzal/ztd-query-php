<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use LogicException;

final class PlanUndefinedException extends LogicException
{
    public static function forClass(string $className): self
    {
        return new self(sprintf(
            '%s::define() needs a plan to build. Override definition() in %s to return '
            . 'the relation string, or build the plan with FixturePlan::from() instead.',
            $className,
            $className
        ));
    }
}
