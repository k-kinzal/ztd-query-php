<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Plugin;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One assignment of INSTALL COMPONENT … SET: `[GLOBAL | PERSIST] [component.]variable = value`.
 *
 * Mirrors PT_install_component_set_element. The scope is the variable's
 * scope: GLOBAL, PERSIST, or none, which the server takes as GLOBAL. The
 * variable is written without at signs. The value is an expression or the
 * word ON.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/install-component.html.
 *
 * @visibility public
 * @example Persisting a component variable
 *     $install = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("INSTALL COMPONENT 'file://c' SET PERSIST c.v = 4");
 *     [$install->statement->settings[0]->variable->scope, $install->statement->settings[0]->variable->instance?->value] // => [\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Persist, 'c']
 */
final class ComponentSetting implements Node
{
    use Snapshot;

    /**
     * @param SystemVariable $variable The variable with its scope and component
     * @param Scalar $value The value: an expression, or OnWord
     * @throws InvalidConstruction When the scope is neither absent, GLOBAL nor PERSIST
     */
    public function __construct(public readonly SystemVariable $variable, public readonly Scalar $value)
    {
        Check::input(in_array($variable->scope, [null, VariableScope::Global, VariableScope::Persist], true), 'A component variable is set GLOBAL or PERSIST.');
    }

    /**
     * Writes the assignment.
     */
    public function render(Output $out): void
    {
        if ($this->variable->scope !== null) {
            $out->keyword($this->variable->scope->value);
        }
        if ($this->variable->instance !== null) {
            $out->name($this->variable->instance, NameUse::Label)->symbol('.');
        }
        $out->name($this->variable->name, NameUse::Label)->symbol('=')->node($this->value);
    }
}
