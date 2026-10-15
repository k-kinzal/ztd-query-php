<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use Fuzz\Target\Servers;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ResourceProcessorsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testResourceProcessorsPreservesNativeValidation(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release, resourceProcessors: (new Servers())->resourceProcessors($native, $release));
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $statements = [
                    "SELECT VCPU_IDS FROM information_schema.RESOURCE_GROUPS WHERE RESOURCE_GROUP_NAME = 'USR_default'",
                    'ALTER RESOURCE GROUP _sqlfaker_identifier VCPU := 00000000009 - 000000245134 0000000000000002145626619 DISABLE FORCE',
                    'CREATE RESOURCE GROUP g TYPE USER VCPU 0',
                    'ALTER RESOURCE GROUP g VCPU 9-245134',
                    'ALTER RESOURCE GROUP g VCPU 245134-9',
                    'ALTER RESOURCE GROUP g VCPU 245134-245135',
                    'ALTER RESOURCE GROUP g VCPU 245135-245134',
                    'ALTER RESOURCE GROUP g VCPU 1-0',
                    'DROP RESOURCE GROUP g',
                ];
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
