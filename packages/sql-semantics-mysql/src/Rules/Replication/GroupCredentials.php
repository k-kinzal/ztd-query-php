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
            $problem = $this->credential($option);
            if ($problem !== null) {
                return $problem;
            }
            $credentials[$option->credential->value] = $option->value->value;
        }
        if (isset($credentials['PASSWORD']) && !isset($credentials['USER'])) {
            return new RefusedSetting(ReplicationError::GroupUserMissing);
        }
        return null;
    }
    /**
     * Reports credential conditions checked while reading an individual option.
     */
    public function credential(CredentialOption $option): ?RefusedSetting
    {
        if ($option->credential === Credential::User && $option->value->value === '') {
            return new RefusedSetting(ReplicationError::GroupUserEmpty);
        }
        return $option->credential === Credential::Password && strlen($option->value->value) > 32 ? new RefusedSetting(ReplicationError::GroupPasswordTooLong) : null;
    }

}
