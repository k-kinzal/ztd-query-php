<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Fixture\Servers;
use Fuzz\Observation\Record;
use Fuzz\Target\ExecutionTarget;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FixtureIdentityTest extends TestCase
{
    public function testRepairUsesTheMatchedHostAndPreservesAtSignsInTheUserName(): void
    {
        $container = Testcontainers::run(new DisposableMySql('8.4.7'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $dsn = 'mysql:host=' . $endpoint->host . ';port=' . $endpoint->port;
            $administrator = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $administrator->exec("CREATE USER 'fixture@segment'@'%' IDENTIFIED BY 'fixture'");
            $connection = new PDO($dsn, 'fixture@segment', 'fixture', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $record = new Record('native', '', '');

            self::assertSame(['fixture@segment', '%'], (new Servers())->principal($connection, $record));
            self::assertNotSame('%', (new Servers())->peer($connection, $record));
            $target = new ExecutionTarget($dsn, 'fixture@segment', 'fixture', $dsn);
            $target->guard($record);
            $administrator->exec("ALTER USER 'fixture@segment'@'%' ACCOUNT LOCK");
            $target->repair($administrator, $record);

            $restored = new PDO($dsn, 'fixture@segment', 'fixture', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            self::assertSame(['fixture@segment', '%'], (new Servers())->principal($restored));
            self::assertSame([['fixture@segment', '%']], (new Servers())->rows($administrator, "SELECT User, Host FROM mysql.user WHERE User LIKE 'fixture%'"));
            $probes = array_column($record->probes, null, 'sql');
            self::assertSame('complete', $probes["CREATE USER IF NOT EXISTS 'fixture@segment'@'%' IDENTIFIED BY 'fixture'"]->phase);
            self::assertSame('complete', $probes['SELECT CURRENT_USER()']->phase);
            self::assertSame('complete', $probes['SELECT USER()']->phase);
        } finally {
            $connection = null;
            $restored = null;
            $administrator = null;
            $target = null;
            $container->stop();
        }
    }
}
