<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DatabaseCharsetOptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d',
            'ALTER DATABASE d COLLATE latin1_bin CHARACTER SET utf8mb4',
            'ALTER DATABASE d COLLATE latin1_bin COLLATE latin1_general_cs',
            'ALTER DATABASE d CHARACTER SET utf8 CHARACTER SET latin1',
            'ALTER DATABASE d CHARACTER SET latin1 CHARACTER SET utf8',
            'ALTER DATABASE d COLLATE latin1_bin CHARACTER SET latin1',
            'ALTER DATABASE d CHARACTER SET utf8mb3',
            'ALTER DATABASE d CHARACTER SET ucs2',
            'ALTER DATABASE d CHARACTER SET DEFAULT CHARACTER SET latin1',
            'ALTER DATABASE d CHARACTER SET latin1 CHARACTER SET DEFAULT',
            'ALTER DATABASE d COLLATE DEFAULT COLLATE latin1_bin',
            'ALTER DATABASE d COLLATE latin1_bin COLLATE DEFAULT',
            'ALTER DATABASE d CHARACTER SET utf8 COLLATE latin1_bin',
            'ALTER DATABASE d CHARACTER SET latin1 COLLATE UTF8_GENERAL_CI',
            'ALTER DATABASE d COLLATE utf8_general_ci',
            'ALTER DATABASE d COLLATE utf8_GENERAL_CI',
            'ALTER DATABASE d COLLATE uTf8_general_ci',
            'ALTER DATABASE d COLLATE UTF8MB3_GENERAL_CI',
            'ALTER DATABASE d COLLATE ucs2_general_ci',
            'ALTER DATABASE d COLLATE utf8_general_ci +',
            'ALTER DATABASE d COLLATE UTF8_GENERAL_CI +',
            'ALTER DATABASE d COLLATE ucs2_general_ci +',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The ordered option inputs and resets
     */
    #[DataProvider('providerReleases')]
    public function testOptionDomainAndDiagnosticOrderMatchMySql(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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
