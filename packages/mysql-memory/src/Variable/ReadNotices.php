<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

use MySqlMemory\Evaluation\Context;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Reports system-variable notices that depend on the resolved read scope.
 *
 * An implicit or SESSION read of gtid_executed is deprecated in MySQL 5.7;
 * GLOBAL reads and MySQL 5.6 do not warn. Observed with all three scope spellings
 * and with show_compatibility_56 enabled and disabled on isolated native servers.
 *
 * @visibility MySqlMemory
 */
final class ReadNotices
{
    /**
     * Records the preparation-time notice after the variable has been resolved.
     */
    public function read(string $name, Scope $scope, Context $context): void
    {
        if ($name === 'gtid_executed' && $scope !== Scope::Global && $context->modes->release === GrammarRelease::MySql5744) {
            $context->diagnostics->warning(1681, "'@@SESSION.GTID_EXECUTED' is deprecated and will be removed in a future release.");
        }
    }
}
