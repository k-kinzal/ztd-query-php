<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use MySqlMemory\Instance;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Catalog;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * Applies the collation selected by the client's handshake response.
 *
 * The negotiated character set controls input identifiers and result conversion
 * before the first SQL request. An unavailable id leaves the instance defaults.
 *
 * @visibility MySqlMemory
 */
final class ConnectionEncoding
{
    /**
     * Finds the negotiated collation in the release's catalog.
     */
    public function collation(Instance $instance, int $id): ?Collation
    {
        $release = GrammarRelease::from('mysql-' . $instance->version);
        foreach (Catalog::shared()->collations as $collation) {
            if ($collation->id === $id && $collation->availableIn($release)) {
                return $collation;
            }
        }

        return null;
    }

    /**
     * Installs changed negotiation settings. A handshake matching the inherited
     * client character set's default collation preserves the global connection
     * and result defaults, including NULL result conversion. Verified on isolated
     * MySQL 5.6, 5.7, 8.0, 8.4 and 9.1 instances.
     */
    public function apply(Session $session, ?Collation $collation): void
    {
        if ($collation === null) {
            return;
        }
        $release = $session->settings()->release();
        $inherited = Charset::named((string) $session->variables->read('character_set_client'));
        if ($inherited?->defaultCollation($release) === $collation) {
            return;
        }
        foreach (['character_set_client', 'character_set_connection', 'character_set_results'] as $name) {
            $session->variables->session[$name] = $collation->charset->nameIn($release);
        }
        $session->variables->session['collation_connection'] = $collation->nameIn($release);
        $session->variables->resultsCollation = $collation;
    }
}
