<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Supplies account state required by an unchanged ordered rename request.
 * Both setup and the original request are observed against the native server.
 * A release-specific identity contradiction gets no invented state witness.
 */
final class RenameHistory
{
    /**
     * @return list<M\Statement> Account changes before repeating the original value
     */
    public function steps(M\Account\RenameUser $node, Session $session): array
    {
        $required = $this->requirements($node, $session);
        $drop = [];
        $create = [];
        foreach ($required ?? [] as [$identity, $present]) {
            $exists = $session->instance->accounts->find($identity) !== null;
            if ($exists === $present) {
                continue;
            }
            $account = new M\Name\AccountName(new Name($identity->user), new Name($identity->host));
            if ($present) {
                $create[] = new M\Account\User\UserSpecification($account);
            } else {
                $drop[] = $account;
            }
        }
        return [...($drop === [] ? [] : [new M\Account\DropUser(false, $drop)]), ...($create === [] ? [] : [new M\Account\CreateUser(false, $create)])];
    }

    /**
     * Tracks the requested initial occupancy, keeping connection identity separate.
     *
     * @return array<string, array{Identity, bool}>|null Initial identities, or a contradiction under this session's release
     */
    public function requirements(M\Account\RenameUser $node, Session $session): ?array
    {
        $names = new Names($session->settings()->release());
        $current = $session->variables->definer;
        $known = [];
        $required = [];
        foreach ($node->renamings as $pair) {
            $from = $pair->from instanceof M\Name\CurrentUser ? $current : $names->identity($pair->from, $session);
            $to = $pair->to instanceof M\Name\CurrentUser ? $current : $names->identity($pair->to, $session);
            foreach ([[$from, true], [$to, false]] as [$identity, $present]) {
                $key = $identity->key();
                if (isset($known[$key]) && $known[$key] !== $present) {
                    return null;
                }
                if (!isset($known[$key])) {
                    $required[$key] = [$identity, $present];
                    $known[$key] = $present;
                }
            }
            $known[$from->key()] = false;
            $known[$to->key()] = true;
            if (!$session->settings()->legacy() && $current->key() === $from->key()) {
                $current = $to;
            }
        }
        return $required;
    }
}
