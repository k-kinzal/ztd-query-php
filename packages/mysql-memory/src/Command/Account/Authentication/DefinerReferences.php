<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Authentication;

use MySqlMemory\Account\Identity;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;

/**
 * Reports stored definer references before modern account existence checks.
 *
 * Each object family contributes one notice, regardless of its number of
 * definitions. Events precede views, stored routines and triggers. Observed
 * through CREATE USER, CREATE USER IF NOT EXISTS and DROP USER histories on
 * MySQL 5.6, 5.7, 8.0, 8.4 and 9.1; legacy releases have no such notice.
 *
 * @visibility MySqlMemory
 */
final class DefinerReferences
{
    /**
     * Records the families that retain this account as their definer.
     */
    public function record(Identity $identity, Session $session, Context $context): void
    {
        if ($session->settings()->legacy()) {
            return;
        }
        $found = [];
        foreach ($session->instance->dictionary->schemas as $schema) {
            $groups = ['an event' => $schema->events, 'a view' => $schema->views, 'a stored routine' => [...array_values($schema->procedures), ...array_values($schema->functions)], 'a trigger' => $schema->triggers];
            foreach ($groups as $kind => $definitions) {
                foreach ($definitions as $definition) {
                    if ($definition->definer === [$identity->user, $identity->host]) {
                        $found[$kind] = true;
                    }
                }
            }
        }
        foreach (['an event', 'a view', 'a stored routine', 'a trigger'] as $kind) {
            if (isset($found[$kind])) {
                $context->diagnostics->warning(AccountError::ReferencedAsDefiner, AccountError::ReferencedAsDefiner->message($identity->user, $identity->host, $kind));
            }
        }
    }
}
