<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Location;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\WriteOrder;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;

/**
 * Preserves observed UPDATE preparation and cleanup boundaries without source SQL.
 * MySQL 5.6 postpones joined assignment values until execution. Legacy joined
 * expression failures append a cleanup condition, while destination failures do not.
 *
 * @visibility MySqlMemory
 */
final class WritePreparation
{
    /**
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, true> Conditions left for execution of a prepared statement
     */
    public function deferred(Node $statement, Session $session, bool $prepared, array $located): array
    {
        if (!$prepared || $session->settings()->release() !== GrammarRelease::MySql5651 || !$statement instanceof Update || count((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->occurrences($statement->tables)) < 2) {
            return [];
        }
        return array_fill_keys(array_keys(array_filter($located, static fn (array $condition): bool => ($condition[1][1][0] ?? null) === WriteOrder::UPDATE_VALUES)), true);
    }

    /**
     * Adds only the cleanup belonging to a reached legacy expression phase.
     *
     * @param list<int> $order The complete semantic preparation position
     */
    public function reported(SqlError $error, Node $statement, Session $session, bool $prepared, array $order): SqlError
    {
        if ($prepared || !$session->settings()->legacy() || !$statement instanceof Update || count((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->occurrences($statement->tables)) < 2 || ($order[0] ?? 0) <= WriteOrder::UPDATE_DESTINATIONS) {
            return $error;
        }
        return new SqlError($error->error, $error->getMessage(), $error, [...$error->following, [StatementError::UnknownError->value, StatementError::UnknownError->message()]], $error->signalled, $error->getCode(), $error->recorded);
    }
}
