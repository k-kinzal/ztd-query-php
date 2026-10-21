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
final class AccountBatchHostTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/account-batch-host.sql');
        self::assertIsString($fixture);
        $statements = [
            "CREATE USER a@'h猫' IDENTIFIED BY 'abc'",
            "CREATE USER b IDENTIFIED WITH missing, c@'h猫' IDENTIFIED BY 'abc'",
            "CREATE USER d@'h猫' IDENTIFIED BY 'abc', e IDENTIFIED WITH missing",
            "CREATE USER f IDENTIFIED WITH missing, g@'h猫'",
            "CREATE USER h IDENTIFIED WITH missing, i@'h猫' IDENTIFIED WITH missing",
            "CREATE USER j@'h猫'",
            "CREATE USER k@'h猫' IDENTIFIED BY 'abc', l@'h猫' IDENTIFIED BY 'abc'",
            "CREATE USER m IDENTIFIED WITH missing, n@'h猫' IDENTIFIED BY 'abc', o@'h猫' IDENTIFIED BY 'abc'",
            $fixture,
            "SELECT User,Host FROM mysql.user WHERE User IN ('c','d','g','n','o') ORDER BY User,Host",
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Successful and refused recipients, rollback and retained warnings
     */
    #[DataProvider('providerReleases')]
    public function testGrantConversionsSurviveBatchRollbackInRecipientOrder(string $release, array $statements, bool $prepared): void
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
