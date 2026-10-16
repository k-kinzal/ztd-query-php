<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Evaluation\Context;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Checks a changed network timeout against the periods of configured channels.
 *
 * A channel keeps its period when the global timeout changes. Each excessive
 * period raises a notice, including when the same timeout is assigned again.
 * Observed through isolated MySQL 8.0.44, 8.4.7 and 9.1.0 servers.
 *
 * @visibility MySqlMemory
 */
final class ReplicationSettings
{
    /**
     * Reports existing periods before the checked global value is stored.
     */
    public function timeout(string $name, Scope $scope, string|int|null $value, Context $context): void
    {
        if ($scope !== Scope::Global || !in_array($name, ['slave_net_timeout', 'replica_net_timeout'], true)) {
            return;
        }
        $legacy = in_array($context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        foreach ($context->variables->instance->registry->replication->heartbeatPeriods as $period) {
            if ($period > (float) $value) {
                $context->warning(AdministrationError::HeartbeatAboveTimeout, $legacy ? 'slave_net_timeout' : 'replica_net_timeout');
            }
        }
    }
}
