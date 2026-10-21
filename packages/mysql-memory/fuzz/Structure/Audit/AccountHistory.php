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
        if ($code === 1141 && $node instanceof M\Utility\Show\Server\ShowGrants && $node->user !== null) {
            return $this->missing([$node->user], $session);
        }
        if ($code === 1141 && $node instanceof M\Account\Privilege\RevokeProxy) {
            return [...$this->missing([$node->proxied, ...array_map(static fn (M\Account\User\UserSpecification $user): M\Name\Account => M\Account\User\Grantee::from($user)->user, $node->users)], $session), new M\Account\Privilege\GrantProxy($node->proxied, $node->users)];
        }
        if ($code !== 1396 || !$node instanceof M\Account\DropUser) {
            return [];
        }
        return $this->missing($node->users, $session);
    }

    /**
     * @param list<M\Name\Account> $users The original referenced accounts
     * @return list<M\Statement> Missing accounts only; existing identities are preserved
     */
    public function missing(array $users, Session $session): array
    {
        $missing = [];
        $names = new Names($session->settings()->release());
        foreach ($users as $user) {
            $identity = $names->identity($user, $session);
            if ($session->instance->accounts->find($identity) === null) {
                $missing[$identity->key()] = new M\Account\User\UserSpecification($user);
            }
        }
        return $missing === [] ? [] : [new M\Account\CreateUser(false, array_values($missing))];
    }
}
