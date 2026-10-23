<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Routine;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Statement\Binding;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;
use SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use WeakMap;

/**
 * Reports retained inspection notices when loading and preparing stored instructions.
 *
 * A shortened database label warns on definition and first load. A table label
 * that cannot resolve warns again whenever preparation is retried. Only semantic
 * instruction identities and facts cross this boundary. Observed on MySQL 5.6,
 * 5.7, 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class InspectionNotices
{
    /**
     * Publishes computed-name and conversion notices belonging to stored inspections.
     */
    public function load(Routine $routine, Session $session): void
    {
        if ($routine->statement === null) {
            return;
        }
        $operation = (new Binding())->operation($session, $routine->statement);
        foreach ($operation->facts->warnings as $warning) {
            $instruction = $operation->facts->warningInstruction($warning);
            if (($instruction instanceof ShowDatabases || $instruction instanceof ShowTables) && ($warning instanceof TrimmedName || $warning instanceof NameConversion)) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
    }

    /**
     * Repeats preparation notices after an unresolved computed table label.
     * First execution consumes the notices already published when loading the definition.
     */
    public function start(Operation $operation, Session $session): void
    {
        if (!$operation->statement instanceof ShowTables || array_filter($operation->facts->diagnostics, static fn ($problem): bool => $problem instanceof MissingColumn) === []) {
            return;
        }
        $cache = $session->preparation->inspections ??= new WeakMap();
        $seen = isset($cache[$operation->statement]);
        $cache[$operation->statement] = true;
        foreach ($seen ? $operation->facts->warnings : [] as $warning) {
            if ($warning instanceof TrimmedName) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
    }
}
