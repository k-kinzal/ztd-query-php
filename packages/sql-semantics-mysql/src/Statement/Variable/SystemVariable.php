<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;

/**
 * A system variable read as a value or named as the target of SET: `@@name`, `@@GLOBAL.name`, `@@instance.name`.
 *
 * A structured variable is written with the name of its instance or
 * component before the variable name; `DEFAULT.name` names the instance
 * `default`. Without a scope the session value is read when the variable has
 * one and the global value otherwise.
 *
 * Rule: MYSQL-SYSTEM-VARIABLE-001. Facts: the type and the NULL fact are
 * those of the variable in the running server, which no context holds; the
 * fact names the variable as missing session state. Diagnostics: none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/structured-system-variables.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class SystemVariable implements Scalar
{
    use Snapshot;

    /**
     * @param Name $name The variable name
     * @param VariableScope|null $scope The scope written before the name; the grammar reads GLOBAL or SESSION in an expression and also PERSIST and PERSIST_ONLY in SET
     * @param Name|null $instance The instance or component name of a structured variable
     */
    public function __construct(public readonly Name $name, public readonly ?VariableScope $scope = null, public readonly ?Name $instance = null)
    {
    }

    /**
     * Derives the dependence on the server.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $subject = 'system variable @@' . ($this->scope === null ? '' : $this->scope->value . '.') . ($this->instance === null ? '' : $this->instance->value . '.') . $this->name->value;

        return new ScalarFact(new Dependent([new SessionState($subject)]), Nullability::Dependent);
    }

    /**
     * Writes both at signs, the scope, the instance and the name without spaces.
     */
    public function render(Output $out): void
    {
        $out->symbol('@')->glue()->symbol('@')->glue();
        if ($this->scope !== null) {
            $out->keyword($this->scope->value)->symbol('.');
        }
        if ($this->instance !== null) {
            $out->name($this->instance, NameUse::Label)->symbol('.');
        }
        $out->name($this->name, NameUse::Label);
    }
}
