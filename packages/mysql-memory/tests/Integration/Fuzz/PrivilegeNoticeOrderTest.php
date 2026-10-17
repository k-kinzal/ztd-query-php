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
final class PrivilegeNoticeOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $matrix = [];
        foreach (['GRANT', 'REVOKE', 'REVOKE IF EXISTS'] as $verb) {
            foreach (['TABLE t', 'd.*', '*.*', 'PROCEDURE t', 'PROCEDURE *.*'] as $level) {
                foreach (['SUPER', 'PERSIST_ONLY,SUPER', 'SUPER,PERSIST_ONLY', 'SELECT(a)'] as $privilege) {
                    $matrix[] = $verb . ' ' . $privilege . ' ON ' . $level . ($verb === 'GRANT' ? ' TO ' : ' FROM ') . 'u@host';
                }
            }
        }
        $statements = [...$matrix, 'CREATE DATABASE d', 'USE d', ...$matrix,
            'GRANT SELECT ON TABLE t TO u@host',
            'GRANT SELECT ON TABLE t TO u@host BROKEN',
            'GRANT SUPER ON TABLE t TO u@host BROKEN',
            'REVOKE SUPER ON TABLE t FROM u@host BROKEN',
            'GRANT SUPER ON TABLE t TO u@host,v@other',
            'REVOKE IF EXISTS PERSIST_ONLY,SUPER ON TABLE t FROM u@host,v@other',
            'GRANT SELECT ON TABLE t TO u@host AS absent',
            'GRANT SUPER ON TABLE t TO u@host AS absent',
            'GRANT SELECT ON *.* TO u@host AS absent',
            "GRANT SELECT(a) ON TABLE t TO u@host,v@'bad@host'",
            "REVOKE SUPER ON TABLE t FROM u@host,v@'bad@host'",
            'CREATE PROCEDURE p() REVOKE IF EXISTS PERSIST_ONLY,SUPER ON TABLE t FROM u@host',
            'CALL p()',
            'CREATE PROCEDURE q() GRANT SUPER ON TABLE t TO u@host',
            'CALL q()',
            'CREATE USER u@host', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1)', ...$matrix,
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerReleases')]
    public function testPrivilegeNoticesPreserveTargetAccountAndInputPrecedence(string $release, array $statements): void
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
