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
final class GroupingNoticesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['SELECT * FROM DUAL GROUP BY USER(),USER() ASC', 'SELECT * GROUP BY 1 ASC', 'SELECT 1 FROM DUAL GROUP BY 1 ASC,1 DESC', 'SELECT 1 FROM DUAL GROUP BY 1 ASC,1 DESC BROKEN', 'SELECT * FROM DUAL GROUP BY 1 ASC,1 DESC', 'SELECT * FROM DUAL GROUP BY 1 ASC HAVING missing', 'SELECT * FROM DUAL GROUP BY 1 ASC WITH ROLLUP', 'CREATE DATABASE grouping_domain', 'USE grouping_domain', 'CREATE PROCEDURE p() SELECT * FROM DUAL GROUP BY 1 ASC', 'CALL p()', 'CREATE PROCEDURE q() SELECT * FROM DUAL GROUP BY 1 ASC,1 DESC', 'CALL q()', 'CREATE PROCEDURE skipped() BEGIN IF 0 THEN SELECT * FROM DUAL GROUP BY 1 ASC; END IF; END', 'CALL skipped()'];
        foreach (['5.6.51', '5.7.44'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerReleases')]
    public function testGroupingNoticesSurviveInputRefusalAndStoredDefinition(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

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
