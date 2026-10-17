<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Process\Disconnection;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class DisconnectionTest extends TestCase
{
    public function testWaitDistinguishesLiveConnectionsFromCompletedDisconnection(): void
    {
        $server = Server::start();
        try {
            $observer = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $other = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $query = $other->query('SELECT CONNECTION_ID()');
            self::assertNotFalse($query);
            $id = $query->fetchColumn();
            self::assertIsInt($id);
            $waiting = new Disconnection();

            self::assertFalse($waiting->wait($observer, $id, true, 0.0));
            self::assertSame(0, $observer->exec('KILL QUERY ' . $id));
            self::assertTrue($waiting->wait($observer, $id, false));
            self::assertFalse($waiting->wait($observer, $id, true, 0.0));
            self::assertSame(0, $observer->exec('KILL CONNECTION ' . $id));
            self::assertTrue($waiting->wait($observer, $id, true));
        } finally {
            $server->stop();
        }
    }

    public function testWaitDoesNotTreatAnUnavailableObserverAsCompletedRemoval(): void
    {
        $server = Server::start();
        try {
            $observer = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } finally {
            $server->stop();
        }

        self::assertFalse((new Disconnection())->wait($observer, 1, true, 0.0));
    }
}
