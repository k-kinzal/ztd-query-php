<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Replication;

use SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

/**
 * Checks the relationship between explicitly supplied group recovery credentials.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GroupCredentials
{
    /**
     * Returns the first intrinsic condition, before consulting replication state.
     *
     * @param list<CredentialOption> $options The options in written order
     */
    public function problem(array $options): ?RefusedSetting
    {
        $credentials = [];
        foreach ($options as $option) {
            if ($option->credential === Credential::Password && strlen($option->value->value) > 32) {
                return new RefusedSetting(ReplicationError::GroupPasswordTooLong);
            }
            $credentials[$option->credential->value] = $option->value->value;
        }
        if (($credentials['USER'] ?? null) === '') {
            return new RefusedSetting(ReplicationError::GroupUserEmpty);
        }
        if (isset($credentials['PASSWORD']) && !isset($credentials['USER'])) {
            return new RefusedSetting(ReplicationError::GroupUserMissing);
        }
        return null;
    }
}
