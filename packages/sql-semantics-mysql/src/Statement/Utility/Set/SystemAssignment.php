<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Utility\VariableAccess;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;

/**
 * An assignment to a system variable named with `@@`: `SET @@GLOBAL.x = v`, `SET @@x = DEFAULT`.
 *
 * Rule: MYSQL-SET-ITEM-001 (system variables). The scope written inside
 * `@@` applies to this variable only; without one the session value is
 * assigned. The variable is derived as a system variable reference, the
 * value as an expression at a position that sees no relation; a keyword
 * value (SetWord) has no facts, and a bare name value is its text
 * (MYSQL-SET-WORD-001). Outside a stored program the target is checked for
 * existence, writability and scope. MySQL 8.0 and later derive the value before
 * checking the target; older releases check the target first.
 * Terminates: the parts are leaves.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a persisted assignment
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SET @@PERSIST.max_connections = 10');
 *     [$set->items[0]->variable->scope, $set->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Persist, 'SET @@PERSIST.max_connections = 10']
 */
final class SystemAssignment implements SetItem
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param SystemTarget $variable The assigned variable with the scope written inside `@@`
     * @param Scalar|BareName|SetWord $value The value: an expression, or a keyword the grammar accepts there
     */
    public function __construct(public readonly SystemTarget $variable, public readonly Scalar|BareName|SetWord $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input(!$value instanceof BareName || $value->system, 'A bare name assigned to a system variable is its text.');
    }

    /**
     * Derives the variable and the value, and checks that SET can change the variable in the scope written; a stored target is resolved now and its writability is checked when it runs.
     */
    public function deriveItem(Derivation $derivation): void
    {
        $valueFirst = !in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        if ($valueFirst && !$this->value instanceof SetWord) {
            (new Operands())->single($derivation->scalar($this->value, $derivation->environment()), $derivation);
        }
        $derivation->scalar($this->variable, $derivation->environment());
        if ($this->variable->instance === null) {
            if ($derivation->inProgram()) {
                $boundary = !$valueFirst ? $this->variable : ($this->value instanceof \SqlSemantics\Statement\Node ? $this->value : $this);
                (new VariableAccess())->find($this->variable->name->value, $derivation, true, $boundary);
            } else {
                (new VariableAccess())->assign($this->variable->name->value, $this->variable->scope, $derivation, $valueFirst && $this->value instanceof \SqlSemantics\Statement\Node ? $this->value : $this->variable);
            }
        } else {
            $this->variable->structured($derivation);
        }
        if (!$valueFirst && !$this->value instanceof SetWord) {
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
