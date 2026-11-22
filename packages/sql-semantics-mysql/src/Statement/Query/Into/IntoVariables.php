<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Into;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

/**
 * `INTO var, ...`: the user variables and stored program variables that receive the columns of the one result row.
 *
 * The query that holds the destination derives the targets and reports a
 * count that differs from the number of columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html.
 *
 * @visibility SqlSemantics
 */
final class IntoVariables implements IntoDestination
{
    use Snapshot;

    /**
     * @var non-empty-list<UserVariable|ProgramVariable> The targets in written order
     */
    public readonly array $targets;

    /**
     * @param list<Scalar> $targets The targets in written order, each a user or stored program variable; at least one
     */
    public function __construct(array $targets)
    {
        $list = [];
        foreach (Check::listOf($targets, Scalar::class, 'INTO names at least one variable.', 1) as $target) {
            Check::input($target instanceof UserVariable || $target instanceof ProgramVariable, 'An INTO target is a user variable or a stored program variable.');
            $list[] = $target;
        }
        $this->targets = $list;
    }

    /**
     * Writes the targets.
     */
    public function render(Output $out): void
    {
        $out->list($this->targets);
    }
}
