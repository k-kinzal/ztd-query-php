<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Hint\ProgramHints;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;

/**
 * Publishes typed stored hints as session load conditions and controls their
 * lifetime across the caller's stored-program diagnostics frame.
 *
 * @visibility MySqlMemory
 */
final class StoredHints
{
    /**
     * Publishes the definition's notices when its session cache loads it.
     */
    public function load(CreateProcedure|CreateFunction|CreateTrigger $definition, Session $session): void
    {
        foreach ((new ProgramHints())->warnings($definition, $session) as [$code, $message]) {
            $session->diagnostics->warning($code, $message);
        }
    }
    /**
     * Retains load conditions after the reached instruction sequence. Resource
     * placement survives one SQL instruction; a later SQL instruction consumes it.
     * Flow tests do not consume it. Other declaration notices remain caller conditions.
     * Observed with single statements, blocks, IF branches and failed table lookups.
     *
     * @param list<array{string, int, string}> $conditions The caller's conditions
     * @param array<int, array<string, string>> $signalled Signal metadata by condition position
     * @return array{list<array{string, int, string}>, array<int, array<string, string>>}
     */
    public function retained(array $conditions, array $signalled, int $sqlStatements): array
    {
        $kept = [];
        $signals = [];
        foreach ($conditions as $position => $condition) {
            if ($sqlStatements > 1 && $condition[1] === StatementError::HintNotSupported->number()) {
                continue;
            }
            if (isset($signalled[$position])) {
                $signals[count($kept)] = $signalled[$position];
            }
            $kept[] = $condition;
        }
        return [$kept, $signals];
    }
}
