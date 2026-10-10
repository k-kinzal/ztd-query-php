<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableRule;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

/**
 * Checks reads of and assignments to system variables against the variables of the release.
 *
 * A variable the release does not have is unknown. A read of the global value of a session
 * variable, or of the session value of a global one, is refused; a read without a scope takes
 * the session value when there is one. An assignment to a read-only variable is refused before
 * its scope is checked; SET GLOBAL needs a global value, SET SESSION a session value that SET
 * may change.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class VariableAccess
{
    /**
     * Answers the variable a read names, or null after reporting why it cannot be read.
     */
    public function read(string $name, ?VariableScope $scope, Derivation $derivation): ?Definition
    {
        $definition = $this->find($name, $derivation);
        if ($definition === null) {
            return null;
        }
        $global = $scope !== null && $scope !== VariableScope::Session;
        if ($global && !$definition->reach->global()) {
            $derivation->report(new VariableMisuse(VariableRule::GlobalOfSessionVariable, $name));

            return null;
        }
        if ($scope === VariableScope::Session && !$definition->reach->session()) {
            $derivation->report(new VariableMisuse(VariableRule::SessionOfGlobalVariable, $name));

            return null;
        }

        return $definition;
    }

    /**
     * Reports why an assignment in a scope cannot change a variable, if it cannot.
     *
     * @param VariableScope|null $scope The scope written, null for the session
     */
    public function assign(string $name, ?VariableScope $scope, Derivation $derivation): void
    {
        $definition = $this->find($name, $derivation, true);
        if ($definition === null) {
            return;
        }
        $global = $scope !== null && $scope !== VariableScope::Session;
        $rule = match (true) {
            $definition->writability === Writability::ReadOnly => VariableRule::ReadOnly,
            $global && !$definition->reach->global() => VariableRule::SetGlobalOfSessionVariable,
            !$global && !$definition->reach->session() => VariableRule::SetSessionOfGlobalVariable,
            !$global && $definition->writability === Writability::GlobalOnly => VariableRule::SessionReadOnly,
            default => null,
        };
        if ($rule !== null) {
            $derivation->report(new VariableMisuse($rule, $name));
        }
    }

    /**
     * Answers the variable of the release with a name, or null after reporting that there is none.
     */
    public function find(string $name, Derivation $derivation, bool $assigned = false): ?Definition
    {
        $definition = SystemVariables::of($derivation->context->profile->grammar)->find($name);
        if ($definition === null) {
            $derivation->report(new UnknownSystemVariable($name, $assigned));
        }

        return $definition;
    }
}
