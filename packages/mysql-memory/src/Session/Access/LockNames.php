<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;

/**
 * Resolves lock names in their retained order before reading later lock specifications.
 *
 * A lock mode warns before its table identity, implicit database and duplicate
 * alias are resolved. A failed name prevents warnings from subsequent locks. Semantic notices supply
 * the release-specific messages; no source text is inspected.
 * Observed on native MySQL 5.6.51 and 5.7.44.
 *
 * @visibility MySqlMemory
 */
final class LockNames
{
    /**
     * Reports only reached semantic notices when a lock name fails to resolve.
     *
     * @throws SqlError When a lock name fails before later lock specifications
     */
    public function check(MySqlStatement $operation, Session $session, bool $read): void
    {
        if (!$operation instanceof LockTables) {
            return;
        }
        $failure = $this->failure($operation, $session);
        if ($failure === null) {
            return;
        }
        [$count, $error] = $failure;
        foreach ($read && !$session->replayed ? $operation->statementInformation()->warnings : [] as $warning) {
            if ($warning instanceof Deprecation && $warning->construct === Deprecated::LowPriorityWrite && $count-- > 0) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
        throw $error;
    }

    /**
     * Resolves completed locks in order, retaining the number of reached mode notices.
     *
     * @return array{int, SqlError}|null The notice prefix and its terminal name error
     */
    public function failure(MySqlStatement $operation, Session $session): ?array
    {
        $statement = $operation;
        assert($statement instanceof LockTables);
        $count = 0;
        foreach ($statement->locks as $lock) {
            $count += (int) ($lock->mode === LockMode::LowPriorityWrite);
            try {
                (new \MySqlMemory\Session\Problem\Identifiers())->check($lock, $session->settings()->release());
            } catch (SqlError $error) {
                return [$count, $error];
            }
            if ($lock->table->schema === null && $session->variables->database === '') {
                return [$count, QueryError::NoDatabase->error()];
            }
            foreach ($operation->statementInformation()->diagnostics as $problem) {
                if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable && $problem->alias === ($lock->alias ?? $lock->table->name)) {
                    return [$count, QueryError::NonUniqueTable->error($problem->alias->value)];
                }
            }
        }

        return null;
    }
}
