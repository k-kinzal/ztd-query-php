<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use Container\Endpoint;
use Fuzz\Target\Servers;
use mysqli;
use MySqlMemory\Account\Credentials;
use MySqlMemory\Account\Identity;
use MySqlMemory\Instance;
use MySqlMemory\Session\Session;
use PDO;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Shares installation inputs before a generated structure is observed.
 * The remote root fixture has no PROXY grants on either server, independently of image defaults.
 * The memory fixture mirrors the two overlapping client connections used during discovery.
 * Administrative discovery is followed by reset binary logs and an empty open-table cache on both sides.
 * No target SQL, target response, implementation, or stored routine body is imported.
 */
final class Fixture
{
    /**
     * Creates the memory session with the container's startup configuration and connection identity.
     * A separate administrative connection keeps fixture discovery out of the observed session.
     */
    public function session(Endpoint $endpoint, mysqli $native, string $release, bool $supervised): Session
    {
        $bootstrap = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $bootstrap->exec('DELETE FROM mysql.proxies_priv WHERE User = ' . $bootstrap->quote($endpoint->username) . " AND Host = '%'");
        $bootstrap->exec('FLUSH PRIVILEGES');
        $servers = new Servers();
        $identity = $servers->rows($bootstrap, 'SELECT USER()')[0][0];
        $host = substr($identity, (int) strrpos($identity, '@') + 1);
        $instance = new Instance($release, $servers->globals($bootstrap, $release), ['test'], $host, $supervised, $servers->routineTimestamps($bootstrap), $servers->resourcePriorities($bootstrap, $release), $servers->resourceProcessors($bootstrap, $release), $servers->help($bootstrap));
        $credentials = new Credentials(GrammarRelease::from('mysql-' . $release));
        foreach (['%', 'localhost'] as $rootHost) {
            $account = $instance->accounts->find(new Identity($endpoint->username, $rootHost));
            if ($account !== null) {
                $account->password = $endpoint->password;
                $account->hash = $credentials->hash($account->plugin, $endpoint->password);
            }
        }

        $session = $instance->connect($endpoint->username, $host, threadId: $native->thread_id);
        $administration = $instance->connect($endpoint->username, $host);
        if ($administration->variables->read('log_bin') === 'ON') {
            $target = new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs((new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->binaryLogs(GrammarRelease::from('mysql-' . $release)));
            $reset = new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset([$target]);
            $bootstrap->exec((new \Fuzz\Structure\Reference())->sql($reset, $administration->semantics()->profile()));
            (new \Fuzz\Structure\Execution())->runIn($administration, $reset, []);
        }
        $bootstrap->exec('FLUSH NO_WRITE_TO_BINLOG TABLES');
        $instance->dictionary->cache->close();
        $administration->close();

        return $session;
    }
}
