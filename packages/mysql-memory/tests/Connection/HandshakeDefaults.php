<?php

declare(strict_types=1);

namespace Tests\Connection;

use Fuzz\Target\Observer;
use PDO;

/**
 * Observes a fresh connection after changing inherited server defaults.
 */
final class HandshakeDefaults
{
    /**
     * @param array{string, string} $case Global assignments followed by a fresh handshake
     */
    public function observe(PDO $guard, string $dsn, string $user, string $password, array $case): string
    {
        $guard->exec($case[0]);
        $client = new PDO($dsn . $case[1], $user, $password);
        return serialize((new Observer())->observe($client, 'SELECT @@character_set_client,@@character_set_connection,@@character_set_results,@@GLOBAL.character_set_results,@@collation_connection', true));
    }
}
