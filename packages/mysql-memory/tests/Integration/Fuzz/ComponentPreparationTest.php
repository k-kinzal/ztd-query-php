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
final class ComponentPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE d',
            'USE d',
            'SET @side=0',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=missing',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=(missing)',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=missing+1',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=d.missing',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=@absent',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=NULL',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=ON',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=1/0',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=(@side:=1)',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=VALUES(missing)',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=COUNT(*)',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL c.x=JSON_VALUE(0,\'\')',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL x=VALUES(missing), y=(@side:=2)',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET GLOBAL x=(@side:=3), y=VALUES(missing)',
            'SELECT @side',
            'INSTALL COMPONENT \'noscheme\' SET x=VALUES(missing)',
            'INSTALL COMPONENT \'noscheme\' SET x=1/0',
            'INSTALL COMPONENT \'noscheme\' SET x=ON',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testValuesAreResolvedInOrderBeforeComponentLoading(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

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
