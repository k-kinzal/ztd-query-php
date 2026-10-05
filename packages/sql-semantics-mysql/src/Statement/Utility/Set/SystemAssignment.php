<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An assignment to a system variable named with `@@`: `SET @@GLOBAL.x = v`, `SET @@x = DEFAULT`.
 *
 * Rule: MYSQL-SET-ITEM-001 (system variables). The scope written inside
 * `@@` applies to this variable only; without one the session value is
 * assigned. The variable is derived as a system variable reference, the
 * value as an expression at a position that sees no relation; a keyword
 * value (SetWord) has no facts, and a bare name value is its text
 * (MYSQL-SET-WORD-001). Diagnostics: none, as the variables of the running
 * server are not part of the context. Terminates: the parts are leaves.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a persisted assignment
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SET @@PERSIST.max_connections = 10');
 *     [$set->statement->items[0]->variable->scope, $set->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Persist, 'SET @@PERSIST.max_connections = 10']
 */
final class SystemAssignment implements SetItem
{
    use Snapshot;

    /**
     * @param SystemVariable $variable The assigned variable with the scope written inside `@@`
     * @param Scalar|SetWord $value The value: an expression, or a keyword the grammar accepts there
     */
    public function __construct(public readonly SystemVariable $variable, public readonly Scalar|SetWord $value)
    {
        Check::input(!$value instanceof BareName || $value->system, 'A bare name assigned to a system variable is its text.');
    }

    /**
     * Derives the variable and the value.
     */
    public function deriveItem(Derivation $derivation): void
    {
        $derivation->scalar($this->variable, $derivation->environment());
        if ($this->value instanceof Scalar) {
            (new Operands())->single($derivation->scalar($this->value, $derivation->environment()), $derivation);
        }
    }

    /**
     * Writes the variable, an equals sign and the value.
     */
    public function render(Output $out): void
    {
        $out->node($this->variable)->symbol('=');
        if ($this->value instanceof SetWord) {
            $out->keyword($this->value->value);
        } else {
            $out->node($this->value);
        }
    }
}
