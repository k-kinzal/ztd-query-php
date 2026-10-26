<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Container\MySqlRelease;
use Fuzz\Container\DisposableMySql;
use Fuzz\Fixture\Connections;
use Fuzz\Observation\OwnerThread;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FixtureConnectionReleaseTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testFixtureRestorationBeginsAfterTheOwnerAndItsLocksAreGone(string $version): void
    {
        $server = Testcontainers::run(new DisposableMySql($version));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $administrator = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $owner = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $identity = (new OwnerThread())->connection($owner);
            $administrator->exec('CREATE TABLE release_fixture (id INT PRIMARY KEY)');
            $administrator->exec('INSERT INTO release_fixture VALUES (1)');
            $owner->exec('BEGIN');
            $owner->exec('INSERT INTO release_fixture VALUES (2)');
            $lock = $owner->query("SELECT GET_LOCK('release_fixture', 0)");
            self::assertNotFalse($lock);
            self::assertSame(1, $lock->fetchColumn());
            $lock->closeCursor();

            (new Connections())->release($administrator, $identity);

            $remaining = $administrator->query('SELECT ID FROM information_schema.PROCESSLIST WHERE ID = ' . $identity);
            self::assertNotFalse($remaining);
            self::assertSame([], $remaining->fetchAll(PDO::FETCH_NUM));
            $rows = $administrator->query('SELECT id FROM release_fixture ORDER BY id');
            self::assertNotFalse($rows);
            self::assertSame([[1]], $rows->fetchAll(PDO::FETCH_NUM));
            $available = $administrator->query("SELECT GET_LOCK('release_fixture', 0)");
            self::assertNotFalse($available);
            self::assertSame(1, $available->fetchColumn());
            $available->closeCursor();
            (new Connections())->release($administrator, $identity);
            $administrator->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            (new Connections())->release($administrator, $identity);
            $administrator->exec("DO RELEASE_LOCK('release_fixture')");
        } finally {
            $lock = null;
            $remaining = null;
            $rows = null;
            $available = null;
            $owner = null;
            $administrator = null;
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (MySqlRelease::versions() as $version) {
            yield $version => [$version];
        }
    }
}
