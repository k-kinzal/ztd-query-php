<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use MySqlMemory\Command\Account\Names;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Scalar;

/**
 * Creates missing account fixtures without replacing the original request.
 * Existing identities are kept; CREATE USER without IF NOT EXISTS also works
 * on MySQL 5.6. Every setup outcome is independently compared with MySQL.
 */
final class AccountHistory
{
    /**
     * @return list<M\Statement> Explicit setup for account identity dependencies
     */
    public function steps(M\Statement|Scalar $node, int $code, Session $session): array
    {
        if ($code === 1396 && $node instanceof M\Account\RenameUser) {
            return (new RenameHistory())->steps($node, $session);
        }
        if ($code !== 1396 || !$node instanceof M\Account\DropUser) {
            return [];
        }
        $missing = [];
        $names = new Names($session->settings()->release());
        foreach ($node->users as $user) {
            $identity = $names->identity($user, $session);
            if ($session->instance->accounts->find($identity) === null) {
                $missing[$identity->key()] = new M\Account\User\UserSpecification($user);
            }
        }
        return $missing === [] ? [] : [new M\Account\CreateUser(false, array_values($missing))];
    }
}
