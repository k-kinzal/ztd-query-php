<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Write;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences;
use SqlSemantics\Platform\MySql\Statement\Relation as R;

/**
 * Separates input opening from join predicate binding for retained UPDATEs.
 * Derived relations bind before the legacy write cleanup phase. MySQL 5.7
 * prepared joined assignments bind after inputs but before ON and WHERE.
 * Each occurrence is planned once; no replacement semantic tree is constructed.
 *
 * @visibility MySqlMemory
 */
final class UpdateInputs
{
    /**
     * Places every input in written order, retaining derived and lateral scopes.
     *
     * @param list<R\TableSource> $tables The original input tree
     * @throws SqlError When a table or derived query cannot bind
     */
    public function scope(array $tables, Planner $planner): Scope
    {
        $scope = new Scope();
        foreach ((new TargetReferences())->occurrences($tables) as $table) {
            $planner->relations->plan($table, $scope);
        }
        $this->conditions($tables, $planner, $scope, true);
        return $scope;
    }

    /**
     * Binds USING while opening inputs, or ON in its later expression phase.
     *
     * @param list<R\TableSource> $tables The original input tree
     * @param bool $usingOnly Whether to bind the input namespace before expression preparation
     * @throws SqlError When an ON or USING condition cannot bind
     */
    public function conditions(array $tables, Planner $planner, Scope $scope, bool $usingOnly = false): void
    {
        foreach ($tables as $table) {
            if ($table instanceof R\JoinedTable) {
                $this->conditions([$table->left, $table->right], $planner, $scope, $usingOnly);
                if (($table->on === null) === $usingOnly) {
                    $references = new TargetReferences();
                    $planner->relations->condition($table, $scope, array_map('spl_object_id', $references->occurrences([$table->left])), array_map('spl_object_id', $references->occurrences([$table->right])));
                }
            } elseif ($table instanceof R\TableList) {
                $this->conditions($table->members, $planner, $scope, $usingOnly);
            } elseif ($table instanceof R\NestedRelation || $table instanceof R\EscapedRelation || $table instanceof R\OdbcJoin) {
                $this->conditions([$table->relation], $planner, $scope, $usingOnly);
            }
        }
    }
}
