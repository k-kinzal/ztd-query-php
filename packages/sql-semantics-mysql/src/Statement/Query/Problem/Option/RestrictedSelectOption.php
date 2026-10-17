<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem\Option;

use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A SELECT modifier outside the query-block role that permits it.
 *
 * @visibility public
 * @example Describing a misplaced priority modifier
 *     (new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::HighPriority))->message() // => "Incorrect usage/placement of 'HIGH_PRIORITY'"
 */
final class RestrictedSelectOption implements Diagnostic
{
    use Snapshot;

    /**
     * @param SelectOption $option The modifier whose placement is invalid
     */
    public function __construct(public readonly SelectOption $option)
    {
    }

    /**
     * Describes the placement refusal using the retained modifier.
     */
    public function message(): string
    {
        return "Incorrect usage/placement of '{$this->option->value}'";
    }
}
