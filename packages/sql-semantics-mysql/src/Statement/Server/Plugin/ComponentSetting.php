<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Plugin;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

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
 *     [$install->settings[0]->variable->scope, $install->settings[0]->variable->instance?->value] // => [\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Persist, 'c']
 */
final class ComponentSetting implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The assigned expression or word. A standalone name is a word, not a column lookup.
     */
    public readonly Scalar|BareName|OnWord $value;

    /**
     * @param SystemTarget $variable The variable with its scope and component
     * @param Scalar|BareName|OnWord $value The value: an expression, a standalone word, or OnWord
     * @throws InvalidConstruction When the scope is neither absent, GLOBAL nor PERSIST
     * @param \SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator The written assignment sign
     */
    public function __construct(public readonly SystemTarget $variable, Scalar|BareName|OnWord $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly \SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator = \SqlSemantics\Platform\MySql\Statement\AssignmentOperator::Equal)
    {
        Check::input(in_array($variable->scope, [null, VariableScope::Global, VariableScope::Persist], true), 'A component variable is set GLOBAL or PERSIST.');
        $unwrapped = $value;
        $parentheses = 0;
        while ($unwrapped instanceof Grouped) {
            $parentheses++;
            $unwrapped = $unwrapped->operand;
        }
        $this->value = $unwrapped instanceof ColumnUse ? new BareName($unwrapped->name, $unwrapped->qualifier, parentheses: $parentheses) : $value;
        Check::input(!$this->value instanceof BareName || $this->value->system, 'A component setting word belongs to a system variable.');
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
        $out->name($this->variable->name, NameUse::Label)->symbol($this->operator->value)->node($this->value);
    }
}
