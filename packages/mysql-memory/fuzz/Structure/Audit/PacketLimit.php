<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use Container\Endpoint;
use Fuzz\Structure\Reference;
use mysqli;
use MySqlMemory\Instance;
use SqlSemantics\Statement\Scalar;

/**
 * Compares a direct expression with a new native session inheriting a packet limit.
 * Full observations are serialized losslessly, including binary rows and metadata.
 */
final class PacketLimit
{
    /**
     * @return array{sql: string, native: string, memory: string, rows: string, sourceText: string} Exact observations and the memory rows for a state-transition witness
     */
    public function compare(Endpoint $endpoint, string $release, Scalar $node, int $limit): array
    {
        $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
        try {
            $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_bin');
            $session = (new Instance($release, globals: ['max_allowed_packet' => $limit, 'character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'character_set_results' => 'utf8mb4', 'collation_connection' => 'utf8mb4_bin']))->connect();
            (new \Fuzz\Structure\Execution())->runIn($session, new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables([new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames(new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4')), new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4_bin')))]));
            $session->text = 'unrelated invalid input';
            $sql = (new Reference())->sql($node, $session->semantics()->profile());
            $expected = (new Native())->observe($native, $sql);
            $actual = (new Memory())->observe($session, $node);

            return ['sql' => $sql, 'native' => serialize($expected), 'memory' => serialize($actual), 'rows' => serialize($actual['results'][0]['rows'] ?? null), 'sourceText' => $session->text];
        } finally {
            $native->close();
        }
    }
}
