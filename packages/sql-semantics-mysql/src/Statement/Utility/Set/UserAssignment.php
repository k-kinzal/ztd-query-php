<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An assignment to a user-defined variable: `SET @x = expr`.
 *
 * Rule: MYSQL-SET-ITEM-001 (user variables). The value is an expression,
 * derived at a position that sees no relation; `=` and `:=` are the same
 * assignment here. The variable is derived as a session value reference.
 * Diagnostics: none. Terminates: the value is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/user-variables.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a user variable assignment
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SET @total := 1 + 2');
 *     [$set->statement->items[0]->variable->name->value, $set->toString()] // => ['total', 'SET @total = 1 + 2']
 */
final class UserAssignment implements SetItem
{
    use Snapshot;

    /**
     * @param UserVariable $variable The assigned variable
     * @param Scalar $value The assigned value
     */
    public function __construct(public readonly UserVariable $variable, public readonly Scalar $value)
    {
    }

    /**
     * Derives the variable and the value.
     */
    public function deriveItem(Derivation $derivation): void
    {
        $derivation->scalar($this->variable, $derivation->environment());
        (new Operands())->single($derivation->scalar($this->value, $derivation->environment()), $derivation);
    }

    /**
     * Writes the variable, an equals sign and the value.
     */
    public function render(Output $out): void
    {
        $out->node($this->variable)->symbol('=')->node($this->value);
    }
}
