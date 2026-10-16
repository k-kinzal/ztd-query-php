<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Hint\Hints;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Problem\Reading;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Operation;

/**
 * Prepares an operation independently of its input transport. Resource-group
 * binding conditions follow name resolution, including a preparation failure;
 * SET_VAR and registration conditions precede it. Observed on MySQL 8.0–9.1.
 *
 * @visibility MySqlMemory
 */
final class Preparation
{
    /**
     * Resolves the operation while preserving native condition order and restoring
     * temporary SET_VAR values on both success and failure.
     *
     * @throws SqlError When preparation rejects the operation
     */
    public function check(Operation $operation, Session $session): void
    {
        (new Execution($session))->parsing($operation, $operation->facts->warnings);
        (new Reading())->read($operation, $session);
        (new DefaultDatabase())->check($operation->statement, $session);
        (new DefaultDatabase())->referenced($operation, $session);
        $application = (new Hints())->apply($operation->statement, $session, true);
        try {
            $application->report($session);
            (new Problems())->raise($operation, $session);
        } catch (SqlError $error) {
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
            }
            foreach ($error->following as [$code, $message]) {
                $session->diagnostics->error($code, $message);
            }
            throw new SqlError($error->error, $error->getMessage(), $error, [], $error->signalled, $error->getCode(), true);
        } finally {
            $application->restore($session);
            foreach ($application->resourceWarnings as [$code, $message]) {
                $session->diagnostics->warning($code, $message);
            }
        }
    }
}
