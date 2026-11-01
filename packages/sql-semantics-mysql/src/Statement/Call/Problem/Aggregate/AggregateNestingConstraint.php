<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate;

use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A nested group or window call that cannot belong to its enclosing aggregate.
 *
 * @visibility public
 * @example Naming a nested window
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Rank, [], new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec());
 *     (new \SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint($call))->windowName() // => 'rank'
 */
final class AggregateNestingConstraint implements Diagnostic
{
    use Snapshot;

    /**
     * @param Aggregate|GroupConcat|JsonObjectAggregate|WindowFunction $call The nested call
     */
    public function __construct(public readonly Aggregate|GroupConcat|JsonObjectAggregate|WindowFunction $call)
    {
    }

    /**
     * Distinguishes a nested window from an ordinary group aggregate.
     */
    public function windowName(): ?string
    {
        if ($this->call instanceof WindowFunction) {
            return strtolower($this->call->kind->value);
        }
        if ($this->call->over === null) {
            return null;
        }
        return $this->call instanceof Aggregate ? strtolower($this->call->function->value) : ($this->call instanceof GroupConcat ? 'group_concat' : 'json_objectagg');
    }

    /**
     * Describes the evaluation scopes that conflict.
     */
    public function message(): string
    {
        return 'An aggregate cannot evaluate this nested group or window call in its argument scope.';
    }
}
