<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Location;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Locations;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Resolution\QueryPreparation;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Operation;

/**
 * Reports semantic name and call conditions in their preparation order.
 * Deferred assignment values remain represented and are checked when execution reaches them.
 *
 * @visibility MySqlMemory
 */
final class Conditions
{
    /**
     * Orders complete conditions without treating a deferred value as an unknown one.
     *
     * @param list<FunctionCall> $calls The unresolved stored calls
     * @param list<Diagnostic> $diagnostics Pending semantic conditions
     * @throws SqlError When a reached semantic condition prevents preparation
     */
    public function check(Operation|QueryPreparation $operation, Session $session, array $calls, array $diagnostics, bool $prepared): void
    {
        [$located, $matched] = (new Locations())->located($operation, $calls, $diagnostics, $session, $prepared);
        (new Problems())->unlocated($diagnostics, $located, $session, $operation->statement);
        $writes = new WritePreparation();
        $deferred = $writes->deferred($operation->statement, $session, $prepared, $located);
        $first = Locations::first(array_diff_key($located, $deferred));
        if ($first !== null) {
            $error = (new Errors())->located($first[0], $first[1][0], isset($matched[spl_object_id($first[0])]), $session, $operation->statement);
            throw $writes->reported($error, $operation->statement, $session, $prepared, $first[1][1]);
        }
        foreach ($calls as $call) {
            if (!isset($deferred[spl_object_id($call)])) {
                throw (new \MySqlMemory\Command\Replication\PurgeErrors())->prepared((new Errors())->routine($call, $session), $operation->statement, $call->schema !== null || $session->variables->database !== '');
            }
        }
        foreach ($diagnostics as $diagnostic) {
            if (!isset($deferred[spl_object_id($diagnostic)])) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
    }
}
