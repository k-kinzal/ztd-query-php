<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An assignment to a variable named without `@@`: `SET x = v`, `SET GLOBAL x = v`, `SET a.x = v`.
 *
 * Rule: MYSQL-SET-ITEM-001 (names). With a scope keyword the name is a
 * system variable assigned in that scope. Without one, inside a stored
 * program the name is a variable the program declares when it declares
 * one, and `NEW.x` in a trigger is a column of the new row; otherwise it is
 * a system variable assigned in the scope inherited from an earlier item
 * (SetVariables::scopeOf()). A qualifier names the component or key cache
 * instance of a structured system variable; `DEFAULT.x` is the instance
 * `default`. The value is derived as an expression at a position that sees
 * no relation, a keyword value has no facts, and a bare name value is its
 * text for a system variable (MYSQL-SET-WORD-001). Diagnostics: none.
 * Terminates: the parts are leaves.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-statement.html,
 * https://dev.mysql.com/doc/refman/8.4/en/structured-system-variables.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a scoped name assignment
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SET SESSION sort_buffer_size = DEFAULT');
 *     [$set->statement->items[0]->scope, $set->statement->items[0]->name->value, $set->statement->items[0]->value] // => [\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Session, 'sort_buffer_size', \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::Default]
 */
final class NameAssignment implements SetItem
{
    use Snapshot;

    /**
     * @param Name $name The assigned name
     * @param Scalar|SetWord $value The value: an expression, or a keyword the grammar accepts there
     * @param VariableScope|null $scope The scope keyword written before the name
     * @param Name|null $qualifier The component, key cache instance or trigger row written before the name
     */
    public function __construct(public readonly Name $name, public readonly Scalar|SetWord $value, public readonly ?VariableScope $scope = null, public readonly ?Name $qualifier = null)
    {
        Check::input(!$value instanceof BareName || $value->system === ($scope !== null), 'A bare name value is known to be text exactly when a scope keyword names a system variable.');
    }

    /**
     * Derives the value.
     */
    public function deriveItem(Derivation $derivation): void
    {
        if ($this->value instanceof Scalar) {
            (new Operands())->single($derivation->scalar($this->value, $derivation->environment()), $derivation);
        }
    }

    /**
     * Writes the scope, the name, an equals sign and the value.
     */
    public function render(Output $out): void
    {
        if ($this->scope !== null) {
            $out->keyword($this->scope->value);
        }
        if ($this->qualifier !== null) {
            $out->name($this->qualifier, NameUse::Label)->symbol('.');
        }
        $out->name($this->name, NameUse::Label)->symbol('=');
        if ($this->value instanceof SetWord) {
            $out->keyword($this->value->value);
        } else {
            $out->node($this->value);
        }
    }
}
