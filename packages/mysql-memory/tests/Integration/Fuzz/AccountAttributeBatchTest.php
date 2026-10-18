<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class AccountAttributeBatchTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE USER \'x\' IDENTIFIED WITH missing ATTRIBUTE \'bad\'',
            'CREATE USER IF NOT EXISTS \'x\' IDENTIFIED WITH missing ATTRIBUTE \'bad\'',
            'CREATE USER \'x\' IDENTIFIED BY \'p\' ATTRIBUTE \'bad\'',
            'CREATE USER IF NOT EXISTS \'x\' IDENTIFIED BY \'p\' ATTRIBUTE \'bad\'',
            'CREATE USER \'x\' IDENTIFIED WITH missing, \'y\' IDENTIFIED BY \'p\' ATTRIBUTE \'bad\'',
            'CREATE USER IF NOT EXISTS \'x\' IDENTIFIED WITH missing, \'y\' IDENTIFIED BY \'p\' ATTRIBUTE \'bad\'',
            'CREATE USER \'x\' IDENTIFIED BY \'p\', \'y\' IDENTIFIED WITH missing ATTRIBUTE \'bad\'',
            'CREATE USER IF NOT EXISTS \'x\' IDENTIFIED BY \'p\', \'y\' IDENTIFIED WITH missing ATTRIBUTE \'bad\'',
            'CREATE USER \'x\' IDENTIFIED WITH missing, \'y\' IDENTIFIED BY \'p\', \'z\' IDENTIFIED WITH absent ATTRIBUTE \'bad\'',
            'CREATE USER IF NOT EXISTS \'x\' IDENTIFIED WITH missing, \'y\' IDENTIFIED BY \'p\', \'z\' IDENTIFIED WITH absent ATTRIBUTE \'bad\'',
            'CREATE USER CURRENT_USER ATTRIBUTE \'bad\'',
            'CREATE USER IF NOT EXISTS CURRENT_USER ATTRIBUTE \'bad\'',
            'CREATE USER CURRENT_USER, \'y\' IDENTIFIED BY \'p\' ATTRIBUTE \'bad\'',
            'CREATE USER IF NOT EXISTS CURRENT_USER, \'y\' IDENTIFIED BY \'p\' ATTRIBUTE \'bad\'',
            'CREATE USER \'x\' IDENTIFIED WITH missing, CURRENT_USER ATTRIBUTE \'bad\'',
            'CREATE USER IF NOT EXISTS \'x\' IDENTIFIED WITH missing, CURRENT_USER ATTRIBUTE \'bad\'',
        ];
        $statements[] = (string) file_get_contents(__DIR__ . '/fixtures/create-user-attribute-batch.sql');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Per-account diagnostics and statement-wide attribute validation
     */
    #[DataProvider('providerReleases')]
    public function testAccountAttributeFailureStopsTheBatchAfterEarlierDiagnostics(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
