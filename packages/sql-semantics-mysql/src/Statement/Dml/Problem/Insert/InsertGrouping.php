<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert;

use SqlSemantics\Platform\MySql\Statement\Call as C;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A VALUES element requests grouping or window evaluation outside a query block.
 *
 * Subqueries own their own grouping scope. This condition retains the complete
 * offending call for a stored instruction, without retaining input text or ASTs.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-restrictions.html.
 *
 * @example Describing an ambient window call
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber, [], new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec());
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\InsertGrouping($call))->windowName() // => 'row_number'
 * @visibility public
 */
final class InsertGrouping implements Diagnostic
{
    use Snapshot;

    /**
     * @param C\Aggregate\Aggregate|C\Aggregate\GroupConcat|C\Aggregate\JsonObjectAggregate|C\Window\WindowFunction $function The ambient group or window invocation
     */
    public function __construct(public readonly C\Aggregate\Aggregate|C\Aggregate\GroupConcat|C\Aggregate\JsonObjectAggregate|C\Window\WindowFunction $function)
    {
    }

    /**
     * Names a window invocation, or distinguishes an ordinary aggregate with null.
     */
    public function windowName(): ?string
    {
        if ($this->function instanceof C\Window\WindowFunction) {
            return strtolower($this->function->kind->value);
        }
        if ($this->function->over === null) {
            return null;
        }
        return $this->function instanceof C\Aggregate\Aggregate ? strtolower($this->function->function->value) : ($this->function instanceof C\Aggregate\GroupConcat ? 'group_concat' : 'json_objectagg');
    }

    /**
     * Describes the missing evaluation role independently of runtime table state.
     */
    public function message(): string
    {
        return $this->windowName() === null ? 'A VALUES element cannot aggregate its destination row.' : 'A VALUES element cannot evaluate a window outside a query block.';
    }
}
