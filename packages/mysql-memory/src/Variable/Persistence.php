<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

/**
 * Keeps saved configuration separate from currently active global values.
 *
 * SET PERSIST checks and changes the global value and saves it. PERSIST_ONLY
 * checks and saves a value without changing the running value. Validation
 * uses a copy of the global maps; a refused assignment changes neither map.
 * The saved configuration belongs to the in-memory instance and is not an
 * operating-system file.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
 *
 * @visibility MySqlMemory
 */
final class Persistence
{
    /**
     * Applies an assignment while preserving its persistence scope.
     *
     * @throws \MySqlMemory\Error\SqlError When the variable or its value is refused
     */
    public function assign(Assigner $assigner, string $name, ?VariableScope $scope, int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null $value, ?Domain $domain, ?string $cache = null): void
    {
        if ($scope !== VariableScope::Persist && $scope !== VariableScope::PersistOnly) {
            $assigner->assign($name, $scope === VariableScope::Global ? Scope::Global : Scope::Session, $value, $domain, $cache);

            return;
        }
        $globals = $assigner->variables->globals;
        $candidate = clone $globals;
        $variables = new Variables($assigner->variables->catalog, $candidate, $assigner->variables->instance);
        $activeCache = $scope === VariableScope::Persist ? $cache : null;
        (new Assigner($variables, $assigner->context))->assign($name, Scope::Global, $value, $domain, $activeCache);
        $definition = $variables->catalog->find($name) ?? throw AdministrationError::UnknownSystemVariable->error($name);
        $key = ($cache === null ? '' : $cache . '.') . $definition->name;
        $globals->persisted[$key] = $activeCache === null ? $candidate->value($definition) : $candidate->cached($activeCache, $definition);
        if ($scope === VariableScope::Persist) {
            $globals->values = $candidate->values;
            $globals->caches = $candidate->caches;
        }
    }
}
