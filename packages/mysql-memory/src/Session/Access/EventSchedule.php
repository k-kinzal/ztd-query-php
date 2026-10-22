<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Resolves schedule table dependencies before event-specific expression restrictions.
 * CTE references use their lexical binding; only an unqualified base table needs
 * the current database. Event bodies retain their separate invocation scope.
 *
 * @visibility MySqlMemory
 */
final class EventSchedule
{
    /**
     * @param list<Operand> $operands Complete schedule expressions, excluding the event body
     * @throws SqlError When a reached table requires an unselected default database
     */
    public function check(array $operands, Session $session): void
    {
        if ($session->variables->database !== '') {
            return;
        }
        $derivation = new Derivation($session->semantics()->context([], session: $session->resolution()));
        foreach ($operands as $operand) {
            $derivation->scalar($operand, $derivation->environment());
        }
        foreach ($derivation->facts()->diagnostics as $problem) {
            if ($problem instanceof MissingTable && $problem->name->schema === null) {
                throw QueryError::NoDatabase->error();
            }
        }
    }
}
