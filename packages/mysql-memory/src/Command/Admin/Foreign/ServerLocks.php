<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin\Foreign;

use MySqlMemory\Command\Access\Locks;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Checks the implicit mysql.servers write before foreign-server catalog changes.
 * MySQL 5.6 reports cached duplicate creation and missing alteration first.
 * Verified through SQL on MySQL 5.6 through 9.1.
 *
 * @visibility MySqlMemory
 */
final class ServerLocks
{
    /**
     * @param string $storedName The bounded storage name used by CREATE SERVER
     * @param string|null $cached The existing cache key used by ALTER SERVER
     * @throws \MySqlMemory\Error\SqlError When mysql.servers lacks a write lock
     */
    public function check(Statement $statement, Session $session, string $storedName, ?string $cached): void
    {
        if ($session->locks === []) {
            return;
        }
        if ($session->settings()->release() === GrammarRelease::MySql5651 && (($statement instanceof CreateServer && isset($session->instance->registry->servers[Registry::key($storedName)])) || ($statement instanceof AlterServer && $cached === null))) {
            return;
        }
        (new Locks())->allowed($session, new QualifiedName(new Name('servers'), new Name('mysql')), 'servers', [], true);
    }
}
